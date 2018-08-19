<?php
$command = $argv[1];
switch ($command){
    case "config":
        showConfig();
        break;
    default:
    case null:
    case "help":
        showHelp();
    break;
}

function showConfig(){
    $config = new ArrayObject();
    if (!extension_loaded('zip')) {
        echo "Установите расширение zip-php";
        echo "sudo apt-get install php-zip";
        exit(1);
    }
    getInput("Введите токен Yandex.oAuth: ", $config, "token",'isValidToken');
    getInput("Укажите директорию, в которой будут храниться temp файлы: ", $config, "tmp_dir", 'isValidDir');

    $mysql = false;
    while ($mysql != true){
        getInput("В целях безопасности, используйте пользователя, у которого есть права только на ЧТЕНИЕ нужных вам баз\nВведите логин mysql-пользователя: ", $config, "db_login");
        $config["db_pass"] = prompt_silent();
        $connection = new mysqli('localhost', $config["db_login"], $config["db_pass"]);
        $mysql = $connection->connect_errno == 0 ? true : false;
        if(!$mysql){
            echo "\nВведены неверные данные\n";
        }
    }
    getInput("Укажите название сайта: ", $config, "name");
    getInput("Укажите как часто нужно делать бекап (В днях): ", $config, "cron_delay");
    getInput("Укажите название базы для дампа (Если не нужно пропустите): ", $config, "db");
    getInput("Укажите директорию сайта для дампа (Если не нужно пропустите): ", $config, "site_dir",'isValidDir');
    $connection->close();
    file_put_contents($config['tmp_dir'].'/config.json', json_encode($config,JSON_PRETTY_PRINT));
    file_put_contents($config['tmp_dir'].'/backuper',file_get_contents(__DIR__.'/backuper.php'));
    shell_exec('crontab -l > mycron');
    shell_exec('echo \'20 4 1-30/'.$config['cron_delay'].' * * php '.$config["tmp_dir"].'/backuper\' >> mycron');
    shell_exec('crontab mycron');
    shell_exec('rm mycron');

    echo "Бекапер успешно установлен и настроен, нажмите любую клавишу чтобы выйти...\n";
}

function showHelp(){
    echo "
==========================================
B)bbbb                   k)                                     
B)   bb                  k)                                     
B)bbbb   a)AAAA   c)CCCC k)  KK u)   UU p)PPPP  e)EEEEE  r)RRR  
B)   bb   a)AAA  c)      k)KK   u)   UU p)   PP e)EEEE  r)   RR 
B)    bb a)   A  c)      k) KK  u)   UU p)   PP e)      r)      
B)bbbbb   a)AAAA  c)CCCC k)  KK  u)UUU  p)PPPP   e)EEEE r)      
                                        p)                      
                                        p)                      
==========================================
php backuper.phar config -- to configure your backup system
==========================================\n";
}

//utils

function getInput($message, $config, $name, $validate =false){
    echo $message;
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    $answer = trim($line);
    if(!$validate || call_user_func($validate,$answer)){
        $config[$name] = $answer;
    }else{
        echo "Валидация не пройдена, введите верные данные \n";
        getInput($message, $config, $name, $validate);
    }

}

function isValidDir($dir){
    if (!file_exists($dir)) {
        $mkdir = mkdir($dir);
        if (!$mkdir) {
            print "Невозможно создать директорию $dir \n";
            return false;
        }
    } elseif (file_exists($dir) && is_dir($dir) && !is_writable($dir)) {
        print "Директория $dir не доступна для записи\n";
        return false;
    }
    return true;
}

function isValidToken($token){
    $ch = curl_init();
    $headers = array(
        'Authorization: OAuth '.$token,
        'Accept: application/json',
    );

    $options = array(
        CURLOPT_URL             => 'https://cloud-api.yandex.net/v1/disk/',
        CURLOPT_RETURNTRANSFER  => TRUE,
        CURLOPT_VERBOSE         => FALSE,
        CURLOPT_HTTPHEADER      => $headers,
    );
    curl_setopt_array($ch, $options);

    $body = json_decode(curl_exec($ch),true);
    $res = curl_getinfo($ch);
    curl_close($ch);

    if ($res['http_code'] === 200) {
        echo('Привет, '.$body['user']['display_name'])."\n";
        return TRUE;
    } else {
        return FALSE;
    }
}

function prompt_silent($prompt = "Введите пароль (Ввод скрыт):") {
    if (preg_match('/^win/i', PHP_OS)) {
        $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
        file_put_contents(
            $vbscript, 'wscript.echo(InputBox("'
            . addslashes($prompt)
            . '", "", "password here"))');
        $command = "cscript //nologo " . escapeshellarg($vbscript);
        $password = rtrim(shell_exec($command));
        unlink($vbscript);
        return $password;
    } else {
        $command = "/usr/bin/env bash -c 'echo OK'";
        if (rtrim(shell_exec($command)) !== 'OK') {
            trigger_error("Can't invoke bash");
            return;
        }
        $command = "/usr/bin/env bash -c 'read -s -p \""
            . addslashes($prompt)
            . "\" mypassword && echo \$mypassword'";
        $password = rtrim(shell_exec($command));
        echo "\n";
        return $password;
    }
}

function cSystem($cmd) {
    $pp = proc_open($cmd, array(STDIN,STDOUT,STDERR), $pipes);
    if(!$pp) return 127;
    return proc_close($pp);
}
