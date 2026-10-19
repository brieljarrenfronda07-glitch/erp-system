<?php
declare(strict_types=1);
$production=getenv('APP_ENV')==='production';
session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Strict','cookie_secure'=>$production,'use_strict_mode'=>true]);
header('Content-Type: application/json');
date_default_timezone_set('Asia/Shanghai');
function respond($data,int $status=200): never { http_response_code($status); echo json_encode($data); exit; }
function activity(PDO $db,?array $actor,string $event,string $entity='',?int $recordId=null,string $details=''): void {
 $q=$db->prepare('INSERT INTO activity_logs(actor_id,actor_name,actor_role,event,entity,record_id,details,occurred_at) VALUES(?,?,?,?,?,?,?,?)');
 $q->execute([$actor['id']??null,$actor['name']??'Unknown user',$actor['role']??'', $event,$entity,$recordId,$details,date('c')]);
}
try {
$dataDir=getenv('DATA_DIR')?:__DIR__.'/data';
if (!is_dir($dataDir)) mkdir($dataDir,0700,true);
$db=new PDO('sqlite:'.$dataDir.'/clinic.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA busy_timeout=5000');
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE IF NOT EXISTS prescription_invoices (consultation_id INTEGER PRIMARY KEY REFERENCES records(id) ON DELETE RESTRICT, invoice_id INTEGER NOT NULL REFERENCES records(id) ON DELETE CASCADE)');
$db->exec('CREATE TABLE IF NOT EXISTS invoice_visits (appointment_id INTEGER PRIMARY KEY REFERENCES records(id) ON DELETE RESTRICT, invoice_id INTEGER UNIQUE NOT NULL REFERENCES records(id) ON DELETE CASCADE)');
$db->exec('CREATE TABLE IF NOT EXISTS activity_logs (id INTEGER PRIMARY KEY, actor_id INTEGER, actor_name TEXT NOT NULL, actor_role TEXT NOT NULL, event TEXT NOT NULL, entity TEXT NOT NULL, record_id INTEGER, details TEXT NOT NULL, occurred_at TEXT NOT NULL); CREATE INDEX IF NOT EXISTS activity_time ON activity_logs(occurred_at)');
$db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT UNIQUE NOT NULL, password TEXT NOT NULL, role TEXT NOT NULL); CREATE TABLE IF NOT EXISTS records (id INTEGER PRIMARY KEY, type TEXT NOT NULL, data TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
if (!$db->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
 $db->exec('BEGIN IMMEDIATE');
 if (!$db->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
 $q=$db->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)');
 $adminEmail=$production?strtolower(trim(getenv('ADMIN_EMAIL')?:'')):'admin@pawcare.local';
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
 $q=$db->prepare('SELECT * FROM users WHERE lower(email)=?');$q->execute([strtolower(trim($input['email']??''))]);$u=$q->fetch(PDO::FETCH_ASSOC);
 if(!$u || !password_verify($input['password']??'',$u['password'])) {activity($db,$u?:null,'Login failed','session',null,'Unsuccessful sign-in attempt');$_SESSION['attempts']=($_SESSION['attempts']??0)+1;$_SESSION['attempt_time']=time();respond(['error'=>'Incorrect email or password.'],401);}
 activity($db,$u,'Login','session',null,'Signed in');session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['attempts']=0;$_SESSION['csrf']=bin2hex(random_bytes(24));respond(['ok'=>true]);
}
if(!isset($_SESSION['uid'])) respond(['error'=>'Please sign in.'],401);
$q=$db->prepare('SELECT id,name,email,role FROM users WHERE id=?');$q->execute([$_SESSION['uid']]);$user=$q->fetch(PDO::FETCH_ASSOC);if(!$user) respond(['error'=>'Please sign in.'],401);
if($action==='logout'){activity($db,$user,'Logout','session',null,'Signed out');session_destroy();respond(['ok'=>true]);}
if($action==='logs') {
 if($user['role']!=='admin')respond(['error'=>'Only administrators can view activity logs.'],403);
 if($_SERVER['REQUEST_METHOD']!=='GET')respond(['error'=>'Method not allowed'],405);
 $term=substr(trim((string)($_GET['q']??'')),0,200);$event=(string)($_GET['event']??'');$offset=max(0,(int)($_GET['offset']??0));$where=[];$values=[];
 if($term!==''){$where[]='(actor_name LIKE ? OR event LIKE ? OR entity LIKE ? OR details LIKE ?)';$values=array_fill(0,4,'%'.$term.'%');}
 if($event!==''){$where[]='event=?';$values[]=$event;}
 $filter=$where?' WHERE '.implode(' AND ',$where):'';
 $q=$db->prepare('SELECT COUNT(*) FROM activity_logs'.$filter);$q->execute($values);$total=(int)$q->fetchColumn();
 $q=$db->prepare('SELECT id,actor_name,actor_role,event,entity,record_id,details,occurred_at FROM activity_logs'.$filter.' ORDER BY id DESC LIMIT 50 OFFSET '.$offset);$q->execute($values);
 respond(['logs'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'offset'=>$offset,'limit'=>50]);
}
if($action==='bootstrap') {
 $records=$db->query('SELECT * FROM records ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);foreach($records as &$r) {$r['data']=json_decode($r['data'],true);if($user['role']!=='admin'&&$r['type']==='inventory')unset($r['data']['price']);}unset($r);
 if($user['role']==='doctor')$records=array_values(array_filter($records,fn($r)=>$r['type']!=='invoices'));
 respond(['user'=>$user,'csrf'=>$_SESSION['csrf'],'records'=>$records,'users'=>$user['role']==='admin'?$db->query('SELECT id,name,email,role FROM users')->fetchAll(PDO::FETCH_ASSOC):[]]);
}
if($action==='reports'){
 if($user['role']!=='admin')respond(['error'=>'Only administrators can view income reports.'],403);
 if($_SERVER['REQUEST_METHOD']!=='GET')respond(['error'=>'Method not allowed'],405);
 $from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));
 foreach([$from,$to] as $date){$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)respond(['error'=>'Use valid report dates.'],422);}
 if($from>$to)respond(['error'=>'The start date must be before the end date.'],422);
 require_once __DIR__.'/reporting.php';respond(serviceIncomeReport($db->query("SELECT id,type,data FROM records WHERE type='invoices'")->fetchAll(PDO::FETCH_ASSOC),$from,$to));
}
if($_SERVER['REQUEST_METHOD']!=='POST') respond(['error'=>'Method not allowed'],405);
if($action==='user') {
 if($user['role']!=='admin') respond(['error'=>'Administrator access required.'],403);
 if(!filter_var($input['email']??'',FILTER_VALIDATE_EMAIL)||strlen($input['password']??'')<8||!trim($input['name']??'')||!in_array($input['role']??'',['admin','staff','doctor'])) respond(['error'=>'Enter a name, valid email, role, and password of at least 8 characters.'],422);
 $db->beginTransaction();$q=$db->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)');$q->execute([trim($input['name']),strtolower(trim($input['email'])),password_hash($input['password'],PASSWORD_DEFAULT),$input['role']]);activity($db,$user,'Account created','users',(int)$db->lastInsertId(),'Created '.$input['role'].' account');$db->commit();respond(['ok'=>true]);
}
$schemas=['owners'=>['name','email','phone','address'],'patients'=>['name','species','breed','owner','sex','birthday','weight','service','status','notes'],'appointments'=>['patient','date','time','service','vet','status','notes'],'consultations'=>['patient','date','vet','diagnosis','treatment','notes'],'vaccinations'=>['patient','vaccine','date','due','notes'],'inventory'=>['name','category','stock','minimum','price','expiry'],'invoices'=>['owner','description','amount','status','date']];
$schemas['patients']=array_merge($schemas['patients'],['color','temperature','registration_date']);
$schemas['appointments'][]='services';
$schemas['appointments']=array_merge($schemas['appointments'],['performed_services','weight','temperature']);
$schemas['consultations']=array_merge($schemas['consultations'],['diet','recheck','license','ptr','s2']);
$schemas['consultations']=array_merge($schemas['consultations'],['medicines','appointment']);
$schemas['invoices']=array_merge($schemas['invoices'],['patient','admitted','diagnosis','charges','takehome','initial','prepared','line_items','appointment']);
$schemas['invoices'][]='prescriptions';
$schemas['diagnostics']=['patient','date','tested','kit','lot','results','vet','license','ptr','s2'];
$schemas['diagnostics'][]='appointment';$schemas['vaccinations'][]='appointment';
$type=$input['type']??'';if(!isset($schemas[$type])) respond(['error'=>'Invalid record type.'],422);
if($user['role']==='doctor'&&(!in_array($type,['patients','appointments','consultations','vaccinations','diagnostics'],true)||($type==='patients'&&empty($input['id']))))respond(['error'=>'Doctor access is limited to patient updates, appointments, and clinical care records.'],403);
if($type==='inventory'&&$user['role']!=='admin') respond(['error'=>'Only administrators can change inventory. Staff can view stock availability.'],403);
$id=(int)($input['id']??0);
if($id){$q=$db->prepare('SELECT COUNT(*) FROM records WHERE id=? AND type=?');$q->execute([$id,$type]);if(!$q->fetchColumn())respond(['error'=>'Record not found.'],404);}
if($action==='delete') {
 if($user['role']!=='admin') respond(['error'=>'Only administrators can delete records.'],403);
 if($type==='consultations'){$q=$db->prepare('SELECT COUNT(*) FROM prescription_invoices WHERE consultation_id=?');$q->execute([$id]);if($q->fetchColumn())respond(['error'=>'This prescription is linked to an invoice. Remove the invoice first.'],409);}
 if($type==='appointments'){$q=$db->prepare('SELECT COUNT(*) FROM invoice_visits WHERE appointment_id=?');$q->execute([$id]);if($q->fetchColumn())respond(['error'=>'This visit has an invoice. Remove the invoice first.'],409);}
 if($type==='appointments'){foreach($db->query("SELECT data FROM records WHERE type IN ('consultations','diagnostics','vaccinations')") as $clinical){$linked=json_decode($clinical['data'],true);if(($linked['appointment']??'')==(string)$id)respond(['error'=>'This visit has clinical records. Keep the visit or remove linked records first.'],409);}}
 foreach($db->query('SELECT type,data FROM records') as $r){$d=json_decode($r['data'],true);if(($type==='owners' && in_array($r['type'],['patients','invoices']) && ($d['owner']??'')==(string)$id)||($type==='patients' && in_array($r['type'],['appointments','consultations','vaccinations','diagnostics','invoices'])&&($d['patient']??'')==(string)$id)) respond(['error'=>'This record is linked to other records. Remove those first.'],409);}
 $db->beginTransaction();$q=$db->prepare('DELETE FROM records WHERE id=? AND type=?');$q->execute([$id,$type]);activity($db,$user,'Deleted',$type,$id,'Deleted record #'.$id);$db->commit();respond(['ok'=>true]);
}
if($action!=='save') respond(['error'=>'Unknown action'],404);
$data=[];foreach($schemas[$type] as $key) {$v=$input['data'][$key]??'';if(!is_scalar($v)||strlen((string)$v)>10000) respond(['error'=>'Invalid field value.'],422);$data[$key]=trim((string)$v);}
if($user['role']==='doctor'&&in_array($type,['consultations','diagnostics'],true))$data['vet']=$user['name'];
if(in_array($type,['consultations','diagnostics','vaccinations'],true)&&$data['appointment']!==''){
 $q=$db->prepare('SELECT data FROM records WHERE id=? AND type=?');$q->execute([$data['appointment'],'appointments']);$linked=json_decode($q->fetchColumn()?:'null',true);
 if(!$linked||($linked['patient']??'')!==$data['patient'])respond(['error'=>'Clinical records must use the patient from the linked visit.'],422);
 if(($linked['status']??'')==='Cancelled')respond(['error'=>'Clinical records cannot be added to a cancelled visit.'],422);
}
if($type==='appointments'&&$data['performed_services']!==''){
 $performed=json_decode($data['performed_services'],true);if(!is_array($performed)||!array_is_list($performed)||count($performed)>20)respond(['error'=>'Invalid completed service list.'],422);
 foreach($performed as $name)if(!is_string($name)||trim($name)===''||strlen($name)>200||str_contains($name,'|')||preg_match('/[\r\n]/',$name))respond(['error'=>'Invalid completed service.'],422);
 $data['performed_services']=json_encode(array_values(array_unique(array_map('trim',$performed))));
}
if($type==='appointments'&&$id){
 $q=$db->prepare('SELECT data FROM records WHERE id=?');$q->execute([$id]);$prior=json_decode($q->fetchColumn()?:'{}',true);
 if(($prior['patient']??'')!==$data['patient']){
  $q=$db->prepare('SELECT COUNT(*) FROM invoice_visits WHERE appointment_id=?');$q->execute([$id]);$linked=(int)$q->fetchColumn();
  foreach($db->query("SELECT data FROM records WHERE type IN ('consultations','diagnostics','vaccinations')") as $row){$clinical=json_decode($row['data'],true);if(($clinical['appointment']??'')==(string)$id)$linked++;}
  if($linked)respond(['error'=>'This visit has care or billing records and cannot be reassigned to another pet.'],409);
 }
}
if($type==='consultations'){
 $previous=[];if($id){$q=$db->prepare('SELECT data FROM records WHERE id=?');$q->execute([$id]);$previous=json_decode($q->fetchColumn()?:'{}',true);}
 if($user['role']==='staff'){
  if($data['medicines']!==''&&$data['medicines']!==($previous['medicines']??'')&&$data['medicines']!=='[]')respond(['error'=>'Only doctors and administrators can prescribe medicines.'],403);
  $data['medicines']=$previous['medicines']??'';
 }
 if($data['medicines']!==''){
  $medicines=json_decode($data['medicines'],true);if(!is_array($medicines)||!array_is_list($medicines)||count($medicines)>50)respond(['error'=>'Invalid medicine list.'],422);
  foreach($medicines as $medicine){if(!is_array($medicine)||!is_string($medicine['name']??null)||trim($medicine['name'])===''||strlen($medicine['name'])>180||str_contains($medicine['name'],'|')||preg_match('/[\r\n]/',$medicine['name'])||!is_numeric($medicine['quantity']??null)||(float)$medicine['quantity']!==(float)(int)$medicine['quantity']||(int)$medicine['quantity']<1||(int)$medicine['quantity']>10000||!is_string($medicine['instructions']??null)||strlen($medicine['instructions'])>2000)respond(['error'=>'Each medicine requires a name, whole quantity, and valid instructions.'],422);}
 }
 if($data['appointment']!==''){$q=$db->prepare('SELECT data FROM records WHERE id=? AND type=?');$q->execute([$data['appointment'],'appointments']);$visit=json_decode($q->fetchColumn()?:'null',true);if(!$visit||($visit['patient']??'')!==$data['patient'])respond(['error'=>'Select a visit belonging to this patient.'],422);}
}
if($type==='appointments'&&$data['services']!==''){
 $selected=json_decode($data['services'],true);
 if(!is_array($selected)||!array_is_list($selected)||count($selected)<1||count($selected)>20)respond(['error'=>'Choose at least one service for the visit.'],422);
 foreach($selected as $service)if(!is_string($service)||trim($service)===''||strlen($service)>200||str_contains($service,"\n")||str_contains($service,'|'))respond(['error'=>'Invalid visit service.'],422);
 $selected=array_values(array_unique(array_map('trim',$selected)));$data['services']=json_encode($selected);$data['service']=implode(', ',$selected);
}
$required=['owners'=>['name','phone'],'patients'=>['name','species','owner'],'appointments'=>['patient','date','time','service'],'consultations'=>['patient','date','diagnosis'],'vaccinations'=>['patient','vaccine','date','due'],'inventory'=>['name','stock','minimum','price'],'invoices'=>['owner','description','amount','date']];
$required['diagnostics']=['patient','date','results'];
foreach($required[$type] as $key) if($data[$key]==='') respond(['error'=>ucfirst($key).' is required.'],422);
if($type==='patients') {
 if($id) {$q=$db->prepare('SELECT data FROM records WHERE id=? AND type=?');$q->execute([$id,$type]);$previous=json_decode($q->fetchColumn()?:'{}',true);$data['status']=$previous['status']??'';$data['service']=$previous['service']??'';}
}
foreach(['stock','minimum','price','amount','weight','initial','takehome'] as $key) if(isset($data[$key])&&$data[$key]!==''&&(!is_numeric($data[$key])||(float)$data[$key]<0)) respond(['error'=>ucfirst($key).' must be a positive number.'],422);
foreach(['owner'=>'owners','patient'=>'patients'] as $key=>$target) if(isset($data[$key])&&$data[$key]!=='') {$q=$db->prepare('SELECT COUNT(*) FROM records WHERE id=? AND type=?');$q->execute([$data[$key],$target]);if(!$q->fetchColumn()) respond(['error'=>'Select an existing '.$key.'.'],422);}
if($type==='invoices' && $data['patient']!=='') {$q=$db->prepare('SELECT data FROM records WHERE id=? AND type=?');$q->execute([$data['patient'],'patients']);$pet=json_decode($q->fetchColumn(),true);if(($pet['owner']??'')!==$data['owner']) respond(['error'=>'The selected patient must belong to the selected owner.'],422);}
if($type==='invoices' && $data['appointment']!==''){
 $q=$db->prepare('SELECT data FROM records WHERE id=? AND type=?');$q->execute([$data['appointment'],'appointments']);$visit=json_decode($q->fetchColumn()?:'null',true);
 if(!$visit||($visit['patient']??'')!==$data['patient'])respond(['error'=>'The invoice must use the patient from the linked visit.'],422);
 if(($visit['status']??'')==='Cancelled')respond(['error'=>'Cancelled visits cannot be billed.'],422);
 $performed=json_decode($visit['performed_services']??'null',true);
 if((is_array($performed)&&!count($performed))||(!is_array($performed)&&($visit['status']??'')!=='Completed'))respond(['error'=>'Record the services performed before billing this visit.'],422);
 $q=$db->prepare('SELECT invoice_id FROM invoice_visits WHERE appointment_id=?');$q->execute([$data['appointment']]);$existingInvoice=$q->fetchColumn();if($existingInvoice&&(int)$existingInvoice!==$id)respond(['error'=>'This visit already has an invoice. Open its existing bill.'],409);
}
if($type==='invoices'){
 $prescriptions=$data['prescriptions']===''?[]:json_decode($data['prescriptions'],true);
 if(!is_array($prescriptions)||!array_is_list($prescriptions)||count($prescriptions)>100)respond(['error'=>'Invalid prescription references.'],422);
 $prescriptions=array_values(array_unique(array_map('intval',$prescriptions)));
 foreach($prescriptions as $prescription){$q=$db->prepare('SELECT data FROM records WHERE id=? AND type=?');$q->execute([$prescription,'consultations']);$clinical=json_decode($q->fetchColumn()?:'null',true);if(!$clinical||($clinical['patient']??'')!==$data['patient'])respond(['error'=>'Prescriptions must belong to the billed pet.'],422);$q=$db->prepare('SELECT invoice_id FROM prescription_invoices WHERE consultation_id=?');$q->execute([$prescription]);$existing=$q->fetchColumn();if($existing&&(int)$existing!==$id)respond(['error'=>'This prescription is already linked to another invoice. Open that invoice.'],409);}
 $data['prescriptions']=json_encode($prescriptions);
}
if($type==='invoices' && $data['charges']!=='') {
 $total=0;foreach(preg_split('/\R/',$data['charges']) as $line){if(trim($line)==='')continue;$parts=explode('|',$line);$charge=trim(array_pop($parts));if(!$parts||!is_numeric($charge)||(float)$charge<0) respond(['error'=>'Use one charge per line: Description | Amount.'],422);$total+=(float)$charge;}
 $data['amount']=(string)round($total+(float)($data['takehome']?:0),2);
}
if($type==='invoices' && $data['line_items']!=='') {
 if($data['patient']==='')respond(['error'=>'Select the pet receiving these services.'],422);
 $items=json_decode($data['line_items'],true);
 if(!is_array($items)||!array_is_list($items)||count($items)<1||count($items)>100)respond(['error'=>'Add between 1 and 100 services.'],422);
 $subtotal=0;$lines=[];$normalized=[];
 foreach($items as $item){
  $name=is_array($item)&&is_string($item['service']??null)?trim($item['service']):'';
  $qty=$item['quantity']??null;$price=$item['price']??null;
  if($name===''||strlen($name)>200||str_contains($name,'|')||str_contains($name,"\n")||str_contains($name,"\r")||!is_numeric($qty)||(float)$qty!==(float)(int)$qty||(int)$qty<1||(int)$qty>10000||!is_numeric($price)||!is_finite((float)$price)||(float)$price<0||(float)$price>10000000)respond(['error'=>'Each service needs a name, a whole quantity from 1–10000, and a valid nonnegative price.'],422);
  $category=$item['category']??'';
  $categories=['Confinement / Admission / Boarding Fee','Injectable Medicines','Oral Medicines','Emergency Fee','Surgery Fee','Anesthesia','Supplies (syringe, underpad, bowl, cannula)','IV insertion (cannula / IV Set)','Pet Food','CBC/Plt','Serum Chemistry Test','Rapid Test Kit','Fecalysis','Earmites Test','Ultrasound','Urine Analysis','Veterinary Nursing Services','Professional Fee','Take Home Medicines','Other Services'];
  if(!is_string($category)||($category!==''&&!in_array($category,$categories,true)))respond(['error'=>'Select a valid billing category.'],422);
  $unit=(int)round((float)$price*100);$cents=$unit*(int)$qty;$subtotal+=$cents;$normalized[]=['service'=>$name,'quantity'=>(int)$qty,'price'=>$unit/100,'category'=>$category];
  $lines[]=$name.' ('.(int)$qty.' × '.number_format($unit/100,2,'.','').') | '.number_format($cents/100,2,'.','');
 }
 $total=$subtotal+(int)round((float)($data['takehome']?:0)*100);$payment=(int)round((float)($data['initial']?:0)*100);
 if($payment>$total)respond(['error'=>'Payment cannot exceed the invoice total.'],422);
 $data['amount']=number_format($total/100,2,'.','');$data['initial']=number_format($payment/100,2,'.','');$data['line_items']=json_encode($normalized);$data['charges']=implode("\n",$lines);$data['description']=implode(', ',array_column($normalized,'service'));$data['status']=$payment>=$total?'Paid':($payment>0?'Partially paid':'Unpaid');
}
$db->beginTransaction();$event=$id?'Updated':'Created';
if($id){$q=$db->prepare('UPDATE records SET data=? WHERE id=? AND type=?');$q->execute([json_encode($data),$id,$type]);}else{$q=$db->prepare('INSERT INTO records(type,data) VALUES(?,?)');$q->execute([$type,json_encode($data)]);$id=(int)$db->lastInsertId();}if($type==='invoices'){$q=$db->prepare('DELETE FROM invoice_visits WHERE invoice_id=?');$q->execute([$id]);if($data['appointment']!==''){$q=$db->prepare('INSERT INTO invoice_visits(appointment_id,invoice_id) VALUES(?,?)');$q->execute([$data['appointment'],$id]);}}if($type==='invoices'){$q=$db->prepare('DELETE FROM prescription_invoices WHERE invoice_id=?');$q->execute([$id]);foreach($prescriptions as $prescription){$q=$db->prepare('INSERT INTO prescription_invoices(consultation_id,invoice_id) VALUES(?,?)');$q->execute([$prescription,$id]);}}activity($db,$user,$event,$type,$id,$event.' record #'.$id);$db->commit();respond(['ok'=>true]);
} catch(PDOException $e){respond(['error'=>str_contains($e->getMessage(),'UNIQUE')?'That email is already registered.':'Database operation failed.'],409);}catch(Throwable $e){respond(['error'=>'Unable to process this request.'],500);}
