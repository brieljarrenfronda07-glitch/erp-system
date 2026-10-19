<?php
declare(strict_types=1);
// Exercise production initialization in isolated temporary storage.
$directory=sys_get_temp_dir().'/petcare-deploy-'.bin2hex(random_bytes(8));
mkdir($directory,0700);
$api=__DIR__.'/api.php';
$password=bin2hex(random_bytes(20));
$code='putenv('.var_export('APP_ENV=production',true).');'
    .'putenv('.var_export('DATA_DIR='.$directory,true).');'
    .'putenv('.var_export('ADMIN_EMAIL=test-admin@example.com',true).');'
    .'putenv('.var_export('ADMIN_PASSWORD='.$password,true).');'
    .'ini_set("session.save_path",'.var_export($directory,true).');'
    .'$_SERVER["REQUEST_METHOD"]="GET";require '.var_export($api,true).';';
try {
    for($run=0;$run<2;$run++){
        $process=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)) throw new RuntimeException('Could not run initialization');
        $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0||$errors!=='')throw new RuntimeException('Initialization failed: '.$errors);
        $response=json_decode($output,true);
        if(($response['error']??'')!=='Please sign in.')throw new RuntimeException('Unexpected initialization response');
    }
    $db=new PDO('sqlite:'.$directory.'/clinic.sqlite');
    $users=$db->query('SELECT * FROM users')->fetchAll(PDO::FETCH_ASSOC);
    if(count($users)!==1||$users[0]['email']!=='test-admin@example.com'||!password_verify($password,$users[0]['password']))throw new RuntimeException('Admin initialization incorrect');
    if((int)$db->query('SELECT COUNT(*) FROM records')->fetchColumn()!==0)throw new RuntimeException('Production seeded demo records');
    $db=null;
    echo "Passed: production admin initialization, password hashing, no demo records, repeat initialization without duplication.\n";
} finally {
    foreach(glob($directory.'/*') as $file)if(is_file($file))unlink($file);
    rmdir($directory);
}
