<?php
declare(strict_types=1);
$production=getenv('APP_ENV')==='production';
session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Strict','cookie_secure'=>$production,'use_strict_mode'=>true]);
header('Content-Type: application/json');
date_default_timezone_set('Asia/Shanghai');
function respond($data,int $status=200): never { http_response_code($status); echo json_encode($data); exit; }
try {
$dataDir=getenv('DATA_DIR')?:__DIR__.'/data';
if (!is_dir($dataDir)) mkdir($dataDir,0700,true);
$db=new PDO('sqlite:'.$dataDir.'/clinic.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA busy_timeout=5000');
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT UNIQUE NOT NULL, password TEXT NOT NULL, role TEXT NOT NULL); CREATE TABLE IF NOT EXISTS records (id INTEGER PRIMARY KEY, type TEXT NOT NULL, data TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
if (!$db->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
 $db->exec('BEGIN IMMEDIATE');
 if (!$db->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
 $q=$db->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)');
 $adminEmail=$production?(getenv('ADMIN_EMAIL')?:''):'admin@pawcare.local';
 $adminPassword=$production?(getenv('ADMIN_PASSWORD')?:''):'Admin123!';
 if(!filter_var($adminEmail,FILTER_VALIDATE_EMAIL)||($production&&strlen($adminPassword)<12)) { $db->exec('ROLLBACK');respond(['error'=>'Configure ADMIN_EMAIL and ADMIN_PASSWORD (at least 12 characters) before first deployment.'],503); }
 $q->execute([getenv('ADMIN_NAME')?:'Clinic Administrator',$adminEmail,password_hash($adminPassword,PASSWORD_DEFAULT),'admin']);
 if(!$production) {
 $q->execute(['Jamie Lee','staff@pawcare.local',password_hash('Staff123!',PASSWORD_DEFAULT),'staff']);
 $seed=[['owners',['name'=>'Olivia Bennett','email'=>'olivia@example.com','phone'=>'0917 555 0123','address'=>'24 Maple Street']],['owners',['name'=>'Noah Williams','email'=>'noah@example.com','phone'=>'0918 555 0456','address'=>'18 Oak Avenue']],['patients',['name'=>'Milo','species'=>'Dog','breed'=>'Golden Retriever','owner'=>'1','sex'=>'Male','birthday'=>'2021-06-12','weight'=>'28.5','status'=>'Healthy','notes'=>'Friendly. Annual wellness check due.']],['patients',['name'=>'Luna','species'=>'Cat','breed'=>'British Shorthair','owner'=>'2','sex'=>'Female','birthday'=>'2022-03-08','weight'=>'4.2','status'=>'Under treatment','notes'=>'Sensitive stomach.']],['appointments',['patient'=>'3','date'=>date('Y-m-d'),'time'=>'09:30','service'=>'Wellness examination','vet'=>'Dr. Sarah Chen','status'=>'Confirmed','notes'=>'Annual checkup']],['appointments',['patient'=>'4','date'=>date('Y-m-d'),'time'=>'11:00','service'=>'Follow-up consultation','vet'=>'Dr. James Wilson','status'=>'Waiting','notes'=>'Review diet']],['inventory',['name'=>'Rabies vaccine','category'=>'Vaccine','stock'=>'24','minimum'=>'10','price'=>'650','expiry'=>'2027-05-01']],['inventory',['name'=>'Amoxicillin 250 mg','category'=>'Medication','stock'=>'8','minimum'=>'15','price'=>'35','expiry'=>'2027-02-01']],['invoices',['owner'=>'1','description'=>'Wellness examination + vaccination','amount'=>'1250','status'=>'Paid','date'=>date('Y-m-d')]],['vaccinations',['patient'=>'3','vaccine'=>'Rabies','date'=>'2026-05-01','due'=>'2027-05-01','notes'=>'Annual booster']]];
 $q=$db->prepare('INSERT INTO records(type,data) VALUES(?,?)'); foreach($seed as [$type,$data]) $q->execute([$type,json_encode($data)]);
 }
 }
 $db->exec('COMMIT');
}
$action=$_GET['action']??'bootstrap';
$input=json_decode(file_get_contents('php://input'),true)??[];
if ($_SERVER['REQUEST_METHOD']==='POST' && !in_array($action,['login'],true) && (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))) respond(['error'=>'Session expired. Refresh and try again.'],403);
if($action==='login') {
 if($_SERVER['REQUEST_METHOD']!=='POST') respond(['error'=>'Method not allowed'],405);
 if(($_SESSION['attempts']??0)>=10 && time()-($_SESSION['attempt_time']??0)<300) respond(['error'=>'Too many attempts. Please wait five minutes.'],429);
 $q=$db->prepare('SELECT * FROM users WHERE email=?');$q->execute([strtolower(trim($input['email']??''))]);$u=$q->fetch(PDO::FETCH_ASSOC);
 if(!$u || !password_verify($input['password']??'',$u['password'])) {$_SESSION['attempts']=($_SESSION['attempts']??0)+1;$_SESSION['attempt_time']=time();respond(['error'=>'Incorrect email or password.'],401);}
 session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['attempts']=0;$_SESSION['csrf']=bin2hex(random_bytes(24));respond(['ok'=>true]);
}
if(!isset($_SESSION['uid'])) respond(['error'=>'Please sign in.'],401);
$q=$db->prepare('SELECT id,name,email,role FROM users WHERE id=?');$q->execute([$_SESSION['uid']]);$user=$q->fetch(PDO::FETCH_ASSOC);if(!$user) respond(['error'=>'Please sign in.'],401);
if($action==='logout'){session_destroy();respond(['ok'=>true]);}
if($action==='bootstrap') {
 $records=$db->query('SELECT * FROM records ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);foreach($records as &$r) $r['data']=json_decode($r['data'],true);unset($r);
 respond(['user'=>$user,'csrf'=>$_SESSION['csrf'],'records'=>$records,'users'=>$user['role']==='admin'?$db->query('SELECT id,name,email,role FROM users')->fetchAll(PDO::FETCH_ASSOC):[]]);
}
if($_SERVER['REQUEST_METHOD']!=='POST') respond(['error'=>'Method not allowed'],405);
if($action==='user') {
 if($user['role']!=='admin') respond(['error'=>'Administrator access required.'],403);
 if(!filter_var($input['email']??'',FILTER_VALIDATE_EMAIL)||strlen($input['password']??'')<8||!trim($input['name']??'')||!in_array($input['role']??'',['admin','staff'])) respond(['error'=>'Enter a name, valid email, role, and password of at least 8 characters.'],422);
 $q=$db->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)');$q->execute([trim($input['name']),strtolower(trim($input['email'])),password_hash($input['password'],PASSWORD_DEFAULT),$input['role']]);respond(['ok'=>true]);
}
$schemas=['owners'=>['name','email','phone','address'],'patients'=>['name','species','breed','owner','sex','birthday','weight','service','status','notes'],'appointments'=>['patient','date','time','service','vet','status','notes'],'consultations'=>['patient','date','vet','diagnosis','treatment','notes'],'vaccinations'=>['patient','vaccine','date','due','notes'],'inventory'=>['name','category','stock','minimum','price','expiry'],'invoices'=>['owner','description','amount','status','date']];
$schemas['patients']=array_merge($schemas['patients'],['color','temperature','registration_date']);
$schemas['consultations']=array_merge($schemas['consultations'],['diet','recheck','license','ptr','s2']);
$schemas['invoices']=array_merge($schemas['invoices'],['patient','admitted','diagnosis','charges','takehome','initial','prepared']);
$schemas['diagnostics']=['patient','date','tested','kit','lot','results','vet','license','ptr','s2'];
$type=$input['type']??'';if(!isset($schemas[$type])) respond(['error'=>'Invalid record type.'],422);
$id=(int)($input['id']??0);
if($action==='delete') {
 if($user['role']!=='admin') respond(['error'=>'Only administrators can delete records.'],403);
 foreach($db->query('SELECT type,data FROM records') as $r){$d=json_decode($r['data'],true);if(($type==='owners' && in_array($r['type'],['patients','invoices']) && ($d['owner']??'')==(string)$id)||($type==='patients' && in_array($r['type'],['appointments','consultations','vaccinations','diagnostics','invoices'])&&($d['patient']??'')==(string)$id)) respond(['error'=>'This record is linked to other records. Remove those first.'],409);}
 $q=$db->prepare('DELETE FROM records WHERE id=? AND type=?');$q->execute([$id,$type]);respond(['ok'=>true]);
}
if($action!=='save') respond(['error'=>'Unknown action'],404);
$data=[];foreach($schemas[$type] as $key) {$v=$input['data'][$key]??'';if(!is_scalar($v)||strlen((string)$v)>10000) respond(['error'=>'Invalid field value.'],422);$data[$key]=trim((string)$v);}
$required=['owners'=>['name','phone'],'patients'=>['name','species','owner','service'],'appointments'=>['patient','date','time','service'],'consultations'=>['patient','date','diagnosis'],'vaccinations'=>['patient','vaccine','date','due'],'inventory'=>['name','stock','minimum','price'],'invoices'=>['owner','description','amount','date']];
$required['diagnostics']=['patient','date','results'];
foreach($required[$type] as $key) if($data[$key]==='') respond(['error'=>ucfirst($key).' is required.'],422);
if($type==='patients') {
 if(!in_array($data['service'],['Consultation','Grooming','Deworming','Surgery','Vaccination','Confinement/Admission'],true)) respond(['error'=>'Select a valid service.'],422);
 if($id) {$q=$db->prepare('SELECT data FROM records WHERE id=? AND type=?');$q->execute([$id,$type]);$previous=json_decode($q->fetchColumn()?:'{}',true);$data['status']=$previous['status']??'';}
}
foreach(['stock','minimum','price','amount','weight','initial','takehome'] as $key) if(isset($data[$key])&&$data[$key]!==''&&(!is_numeric($data[$key])||(float)$data[$key]<0)) respond(['error'=>ucfirst($key).' must be a positive number.'],422);
foreach(['owner'=>'owners','patient'=>'patients'] as $key=>$target) if(isset($data[$key])&&$data[$key]!=='') {$q=$db->prepare('SELECT COUNT(*) FROM records WHERE id=? AND type=?');$q->execute([$data[$key],$target]);if(!$q->fetchColumn()) respond(['error'=>'Select an existing '.$key.'.'],422);}
if($type==='invoices' && $data['patient']!=='') {$q=$db->prepare('SELECT data FROM records WHERE id=? AND type=?');$q->execute([$data['patient'],'patients']);$pet=json_decode($q->fetchColumn(),true);if(($pet['owner']??'')!==$data['owner']) respond(['error'=>'The selected patient must belong to the selected owner.'],422);}
if($type==='invoices' && $data['charges']!=='') {
 $total=0;foreach(preg_split('/\R/',$data['charges']) as $line){if(trim($line)==='')continue;$parts=explode('|',$line);$charge=trim(array_pop($parts));if(!$parts||!is_numeric($charge)||(float)$charge<0) respond(['error'=>'Use one charge per line: Description | Amount.'],422);$total+=(float)$charge;}
 $data['amount']=(string)round($total+(float)($data['takehome']?:0),2);
}
if($id){$q=$db->prepare('UPDATE records SET data=? WHERE id=? AND type=?');$q->execute([json_encode($data),$id,$type]);}else{$q=$db->prepare('INSERT INTO records(type,data) VALUES(?,?)');$q->execute([$type,json_encode($data)]);}respond(['ok'=>true]);
} catch(PDOException $e){respond(['error'=>str_contains($e->getMessage(),'UNIQUE')?'That email is already registered.':'Database operation failed.'],409);}catch(Throwable $e){respond(['error'=>'Unable to process this request.'],500);}
