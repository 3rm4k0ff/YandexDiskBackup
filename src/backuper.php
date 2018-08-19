<?php
//curl-php
$config = json_decode(file_get_contents('config.json'),true);

date_default_timezone_set('UTC');
$date = date('Ymd_His').'UTC'; // Дата создания бэкапа
$tmpDir = $config['tmp_dir'];


if (getDiskInfo()) {
    $archive = $config['tmp_dir']."/{$config['name']}_{$date}.zip";

    if(!empty($config['site_dir'] && file_exists($config['site_dir']) && is_dir($config['site_dir']))) {
      createZipArchive($config['site_dir'], $archive);
    }

    if (!empty($config['db'])) {
         $dump = "{$config['tmp_dir']}/{$config['name']}_dump_{$date}.sql";
         createMysqlDump($config['db'], $config['db_login'], $config['db_pass'], $dump);

         if(!empty($config['site_dir'])){
             appendFileToZipArchive($dump, $archive);
         }else{
             createZipArchive($dump, $archive);
         }
        unlink($dump);
      }

      if(!getDiskFileInfo($config['name'])) {
          createDiskDir($config['name']);
      }

      $upload = uploadFileToDisk($archive, $config['name']);
      if ($upload) {
          print "Файл $archive загружен.\n";
          unlink($archive);
      } else {
          print "Ошибка при загрузке файла $archive \n";
      }
} else {
  print "Ошибка при подключении к Яндекс.Диску\n";
  exit(1);
}


/**
 * Проверка подключения к диску и вывод базовой информации
 */
function getDiskInfo() {
  global $config;

  $ch = curl_init();

  $headers = array(
    'Authorization: OAuth '.$config['token'],
    'Accept: application/json',
  );

  $options = array(
      CURLOPT_URL             => 'https://cloud-api.yandex.net/v1/disk/',
      CURLOPT_RETURNTRANSFER  => TRUE,
      CURLOPT_VERBOSE         => FALSE,
      CURLOPT_HTTPHEADER      => $headers,
    );
  curl_setopt_array($ch, $options);

  $body = curl_exec($ch);
  $res = curl_getinfo($ch);
  curl_close($ch);

  if ($res['http_code'] === 200) {
    return TRUE;
  } else {
    return FALSE;
  }
}

/**
 * Получение информации о файле или папке
 *
 * @param string $path Путь к файлу/папке в папке приложения
 */
function getDiskFileInfo($path) {
	global $config;
	$baseUrl = 'https://cloud-api.yandex.net/v1/disk/resources?';
	$url = $baseUrl . http_build_query(array(
		'path'	=> 	'app:/'.$path
	));

  $ch = curl_init();

  $headers = array(
    'Authorization: OAuth '.$config['token'],
    'Accept: application/json',
  );

  $options = array(
      CURLOPT_URL             => $url,
      CURLOPT_RETURNTRANSFER  => TRUE,
      CURLOPT_VERBOSE         => FALSE,
      CURLOPT_HTTPHEADER      => $headers,
    );
  curl_setopt_array($ch, $options);

  $body = curl_exec($ch);
  $res = curl_getinfo($ch);
  curl_close($ch);

  if ($res['http_code'] === 200) {
    return TRUE;
  } else {
    return FALSE;
  }
}

/**
 * Создает директорию в папке приложения на Я.Диске
 *
 * @param string $name Имя директории
 */
function createDiskDir($name) {
  global $config;
	$baseUrl = 'https://cloud-api.yandex.net/v1/disk/resources/?';
	$url = $baseUrl . http_build_query(array(
		'path'	=>	'app:/'.$name,
	));

  $ch = curl_init();

  $headers = array(
    'Authorization: OAuth '.$config['token'],
    'Accept: application/json',
  );

	$options = array(
      CURLOPT_URL             => $url,
      CURLOPT_RETURNTRANSFER  => TRUE,
      CURLOPT_VERBOSE         => FALSE,
      CURLOPT_HTTPHEADER      => $headers,
			CURLOPT_CUSTOMREQUEST   => 'PUT',
    );
  curl_setopt_array($ch, $options);

	$body = curl_exec($ch);
  $res = curl_getinfo($ch);
  curl_close($ch);

  // 201 - Created; 409 - Conflict (папка уже существует)
	if ($res['http_code'] === 201 || $res['http_code'] === 409) {
    return TRUE;
  } else {
    return FALSE;
  }
}

/**
 * Загружает файл на диск
 *
 * @param string $filename Имя файла, который надо загрузить
 * @param string $dir      Папка сайта на Я.Диске
 */
function uploadFileToDisk($filename, $dir = '') {
	global $config;
  $baseUrl = 'https://cloud-api.yandex.net/v1/disk/resources/upload?';
  $url = $baseUrl . http_build_query(array(
    'path'  =>  $dir ? 'app:/'."$dir/" . basename($filename) : 'app:/' . basename($filename),
  ));

	if (file_exists($filename)) {
      $ch = curl_init();

  		$headers = array(
    			'Authorization: OAuth ' . $config['token'],
    			'Accept: application/json',
  		);

  		$getOptions = array(
      		CURLOPT_URL             => $url,
      		CURLOPT_RETURNTRANSFER  => TRUE,
      		CURLOPT_VERBOSE         => FALSE,
      		CURLOPT_HTTPHEADER      => $headers,
  		);
  		curl_setopt_array($ch, $getOptions);

  		$body = json_decode(curl_exec($ch), TRUE);
  		$res = curl_getinfo($ch);

			if ($res['http_code'] === 200) {
          $file = fopen($filename, 'r');
  				$putOptions = array(
							CURLOPT_URL             => $body['href'],
      				CURLOPT_RETURNTRANSFER  => TRUE,
      				CURLOPT_VERBOSE         => FALSE,
      		    CURLOPT_PUT             => TRUE,
              CURLOPT_HTTPHEADER      => $headers,
              CURLOPT_INFILE          => $file,
              CURLOPT_INFILESIZE      => filesize($filename),
					);
          curl_setopt_array($ch, $putOptions);

					$body = curl_exec($ch);
  				$res = curl_getinfo($ch);
          fclose($file);
			}
			curl_close($ch);

  		if ($res['http_code'] === 201 || $res['http_code'] === 202) {
    			return TRUE;
  		} else {
    			return FALSE;
  		}
	} else {
			return FALSE;
	}
}

/**
 * Создание Zip архива из файла или директории
 *
 * @param string $source Директория или файл, которую надо запаковать
 * @param string $destination Имя файла zip архива
 */
function createZipArchive($source, $destination) {
	if (extension_loaded('zip')) {

		if (file_exists($source)) {
			$zip = new ZipArchive();

			if ($zip->open($destination, ZIPARCHIVE::CREATE)) {
				$source = realpath($source);

				if (is_dir($source)) {

					$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

					foreach ($files as $file) {
						$file = realpath($file);

						if ( is_dir($file) === TRUE ) {
							$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
						} else if ( is_file($file) === TRUE ) {
							$zip->addFile($file, str_replace($source . '/', '', $file));
						}
					}

				} else if ( is_file($source) === TRUE ) {
					$zip->addFile($source, basename($source));
				}
			}
			return $zip->close();
		}
	}
	echo 'Zip extension is not installed';
	return FALSE;
}

/**
 * Добавление файла к zip архиву
 *
 * @param string $file Имя добавляемого файла
 * @param string $archive Имя архива
 */
function appendFileToZipArchive($file, $archive) {
  if (extension_loaded('zip')) {

    if (file_exists($file)) {
      $zip = new ZipArchive();

      if ($zip->open($archive, ZIPARCHIVE::CREATE)) {
        $zip->AddFile($file, basename($file));

        return $zip->close();
      }
    }
  }
  return FALSE;
}

/**
 * Создание Mysql дампа
 *
 * @param string $db Имя базы данных
 * @param string $username Имя пользователя Mysql
 * @param string $password Пароль пользователя Mysql
 * @param string $dumpFile Имя файла дампа
 */
function createMysqlDump($db, $username, $password, $dumpFile) {
  system("mysqldump -u$username -p$password --databases $db > $dumpFile");
}
