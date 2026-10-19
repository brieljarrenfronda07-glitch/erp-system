<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/petcare-audit-'.bin2hex(random_bytes(8));mkdir($dir,0700);
$db=new PDO('sqlite:'.$dir.'/clinic.sqlite');
$db->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT,email TEXT,password TEXT,role TEXT)');
$q=$db->prepare('INSERT INTO users VALUES(?,?,?,?,?)');
$q->execute([1,'Admin','admin@example.com',password_hash('Test-only-password',PASSWORD_DEFAULT),'admin']);
$q->execute([2,'Staff','staff@example.com',password_hash('Test-only-password',PASSWORD_DEFAULT),'staff']);
function requestAudit(string $dir,int $uid,array $query): array {
 $code='putenv('.var_export('DATA_DIR='.$dir,true).');ini_set("session.save_path",'.var_export($dir,true).');session_start();$_SESSION["uid"]='.$uid.';$_SESSION["csrf"]="test";session_write_close();$_GET='.var_export($query,true).';$_SERVER["REQUEST_METHOD"]="GET";require '.var_export(__DIR__.'/api.php',true).';';
 $p=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);
 if($exit!==0||$errors!=='')throw new RuntimeException($errors?:'Request failed');return json_decode($output,true,512,JSON_THROW_ON_ERROR);
}
function checkAudit(bool $condition,string $label): void {if(!$condition)throw new RuntimeException($label);}
try{
 $admin=requestAudit($dir,1,['action'=>'logs']);checkAudit($admin['total']===0,'Admin empty log access');
 $q=$db->prepare('INSERT INTO activity_logs(actor_id,actor_name,actor_role,event,entity,record_id,details,occurred_at) VALUES(?,?,?,?,?,?,?,?)');
 for($i=1;$i<=53;$i++)$q->execute([2,'Staff','staff',$i===1?'Deleted':'Created','patients',$i,'Test record #'.$i,date('c')]);
 $admin=requestAudit($dir,1,['action'=>'logs']);checkAudit($admin['total']===53&&count($admin['logs'])===50&&$admin['logs'][0]['record_id']===53,'Descending pagination');
 $next=requestAudit($dir,1,['action'=>'logs','offset'=>50]);checkAudit(count($next['logs'])===3,'Next page');
 $filtered=requestAudit($dir,1,['action'=>'logs','event'=>'Deleted','q'=>'Staff']);checkAudit($filtered['total']===1,'Search and action filter');
 $staff=requestAudit($dir,2,['action'=>'logs']);checkAudit(isset($staff['error'])&&!isset($staff['logs']),'Staff denied');
 $guest=requestAudit($dir,999,['action'=>'logs']);checkAudit(isset($guest['error'])&&!isset($guest['logs']),'Invalid session denied');
 $bootstrap=requestAudit($dir,2,['action'=>'bootstrap']);checkAudit(!isset($bootstrap['logs'])&&$bootstrap['users']===[],'Staff bootstrap excludes audit log and accounts');
 echo "Passed: admin log access, staff and invalid-session denial, search, action filters, newest-first order, pagination, bootstrap privacy.\n";
}finally{$q=null;$db=null;foreach(glob($dir.'/*') as $file)if(is_file($file))unlink($file);rmdir($dir);}

