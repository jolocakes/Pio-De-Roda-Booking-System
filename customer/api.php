<?php
require_once __DIR__ . '/../includes/db.php';
date_default_timezone_set('Asia/Manila');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', '0');
set_exception_handler(static function (Throwable $e): void { json_response(['ok'=>false,'reason'=>'NETWORK'],500); });
$action = $_GET['action'] ?? '';
try { $pdo = db(); } catch (Throwable $e) { json_response(['ok'=>false,'reason'=>'NETWORK'],503); }
if ($action === 'availability' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $q=$pdo->query("SELECT DISTINCT DATE_FORMAT(event_date,'%Y-%m-%d') AS d FROM reservations WHERE status IN ('Pending','Confirmed')");
    json_response(['dates'=>array_column($q->fetchAll(),'d')]);
}
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $d=request_json();
    $name=trim((string)($d['customerName']??'')); $phone=(string)($d['contactNumber']??''); $email=strtolower(trim((string)($d['email']??'')));
    $date=(string)($d['eventDate']??''); $time=(string)($d['eventTime']??''); $guests=filter_var($d['guests']??null,FILTER_VALIDATE_INT);
    $service=trim((string)($d['mainService']??'')); $package=trim((string)($d['selectedPackage']??'')); $type=trim((string)($d['eventType']??''));
    $validServices=['Catering with Menu','Venue Rentals','Dine-In Menu','Non-Coffee Booth Package','Coffee Booth Package','Pizza Booth','Intimate Celebration Package','Equipment Rental'];
    $packages=[
      'Catering with Menu'=>['2 Main Courses'=>1000,'3 Main Courses'=>1100,'4 Main Courses'=>1300],
      'Venue Rentals'=>['Extension Hall (30 - 35 Persons)'=>15000,'Extension Hall (36 - 50 Persons)'=>20000,'Main Hall (50 - 65 Persons)'=>25000,'Main Hall (66 - 100 Persons)'=>30000],
      'Dine-In Menu'=>['Menu A'=>600,'Menu B'=>600],
      'Non-Coffee Booth Package'=>['Sparkling Refreshers (50 Cups)'=>4250,'Sparkling Refreshers (100 Cups)'=>8500,'Creamy Milk Tea (50 Cups)'=>4250,'Creamy Milk Tea (100 Cups)'=>8500],
      'Coffee Booth Package'=>['Intimate Package (50 Cups)'=>6250,'Intimate Package (100 Cups)'=>12500,'Classic Package (50 Cups)'=>7250,'Classic Package (100 Cups)'=>14500,'Ultimate Package (50 Cups)'=>8250,'Ultimate Package (100 Cups)'=>16500],
      'Pizza Booth'=>['Intimate Package (50 Persons) with 15 Pizza'=>7500,'Intimate Package (50 Persons) with 20 Pizza'=>10000,'Classic Package (80 Persons) with 25 Pizza'=>12000,'Classic Package (80 Persons) with 30 Pizza'=>14500],
      'Intimate Celebration Package'=>['Intimate Package'=>4000,'Classic Package'=>8000,'Ultimate Package'=>14000],
      'Equipment Rental'=>['Standard Rate'=>300]
    ];
    $today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    $dateObj=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    $timeOk=preg_match('/^(?:0[8-9]|1[0-9]|2[01]):00$/',$time)===1;
    if ($name==='' || strlen($name)>640 || !preg_match('/^09\d{9}$/',$phone) || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || !$dateObj || $dateObj->format('Y-m-d')!==$date || $date<$today || !$timeOk || !$guests || $guests<1 || $guests>500 || !in_array($service,$validServices,true) || !isset($packages[$service][$package]) || $type==='' || strlen($type)>400) json_response(['ok'=>false,'reason'=>'INVALID'],400);
    $courses=$service==='Catering with Menu' && is_array($d['mainCourses']??null)?array_values(array_unique(array_map('strval',$d['mainCourses']))):[];
    if ($service==='Catering with Menu' && (count($courses)<2 || count($courses)>4 || count($courses)!==(int)substr($package,0,1) || array_diff($courses,['Chicken Recipe','Beef Recipe','Pork Recipe','Fish or Seafood Recipe']))) json_response(['ok'=>false,'reason'=>'INVALID'],400);
    $limits=['Catering with Menu'=>[50,150],'Venue Rentals'=>[30,100],'Dine-In Menu'=>[1,500],'Non-Coffee Booth Package'=>[1,80],'Coffee Booth Package'=>[1,80],'Pizza Booth'=>[1,80],'Intimate Celebration Package'=>[1,35],'Equipment Rental'=>[1,500]];
    [$minGuests,$maxGuests]=$limits[$service];
    if($guests<$minGuests || $guests>$maxGuests) json_response(['ok'=>false,'reason'=>'INVALID'],400);
    if($service==='Venue Rentals') {
      $range=['Extension Hall (30 - 35 Persons)'=>[30,35],'Extension Hall (36 - 50 Persons)'=>[36,50],'Main Hall (50 - 65 Persons)'=>[50,65],'Main Hall (66 - 100 Persons)'=>[66,100]][$package];
      if($guests<$range[0] || $guests>$range[1]) json_response(['ok'=>false,'reason'=>'INVALID'],400);
    }
    $base=(float)$packages[$service][$package];
    if ($service==='Catering with Menu' || $service==='Dine-In Menu') $base *= $guests;
    $allowedExtras=['Catering with Menu'=>1000,'Venue Rentals'=>15000,'Non-Coffee Booth Package'=>4250,'Coffee Booth Package'=>6250,'Pizza Booth'=>7500,'Intimate Celebration Package'=>4000];
    $extras=[]; $total=$base;
    $requestedExtras=$d['additionalServices']??[];
    if(!is_array($requestedExtras) || count($requestedExtras)>8) json_response(['ok'=>false,'reason'=>'INVALID'],400);
    foreach ($requestedExtras as $item) {
       if (!is_array($item) || !isset($allowedExtras[$item['name']??''])) json_response(['ok'=>false,'reason'=>'INVALID'],400);
       if(($item['name']??'')===$service) json_response(['ok'=>false,'reason'=>'INVALID'],400);
       $qty=filter_var($item['quantity']??1,FILTER_VALIDATE_INT); if(!$qty || $qty<1 || $qty>100) json_response(['ok'=>false,'reason'=>'INVALID'],400);
       $price=$allowedExtras[$item['name']]; $extras[]=['name'=>$item['name'],'quantity'=>$qty,'unitPrice'=>$price,'totalPrice'=>$price*$qty]; $total += $price*$qty;
    }
    try {
      $pdo->beginTransaction();
      $check=$pdo->prepare("SELECT id FROM reservations WHERE event_date=? AND status IN ('Pending','Confirmed') FOR UPDATE"); $check->execute([$date]);
      if($check->fetch()) { $pdo->rollBack(); json_response(['ok'=>false,'reason'=>'DATE_TAKEN'],409); }
      $code=sprintf('PDR-%s-%06d',date('Y'),random_int(0,999999)); $pin=(string)random_int(100000,999999);
      $q=$pdo->prepare('INSERT INTO reservations (reservation_code,booking_pin_hash,customer_name,contact_number,email,event_date,event_time,event_type,guests,main_service,selected_package,main_courses,additional_services,estimated_total,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'Pending\')');
      $q->execute([$code,password_hash($pin,PASSWORD_DEFAULT),$name,$phone,$email,$date,$time,$type,$guests,$service,$package,json_encode($courses),json_encode($extras),$total]);
      $id=(int)$pdo->lastInsertId(); $h=$pdo->prepare('INSERT INTO reservation_status_history (reservation_id,new_status) VALUES (?,\'Pending\')'); $h->execute([$id]); $pdo->commit();
      json_response(['ok'=>true,'reservationId'=>$code,'bookingPin'=>$pin,'status'=>'Pending'],201);
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); json_response(['ok'=>false,'reason'=>'NETWORK'],500); }
}
if ($action === 'lookup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $d=request_json(); $code=strtoupper(trim((string)($d['reservationId']??''))); $pin=(string)($d['bookingPin']??'');
  if(!preg_match('/^PDR-\d{4}-\d{4,}$/',$code)||!preg_match('/^\d{6}$/',$pin)) json_response(['ok'=>false,'reason'=>'NOT_FOUND'],404);
  $ipHash=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
  $pdo->prepare('INSERT IGNORE INTO reservation_lookup_attempts (ip_hash) VALUES (?)')->execute([$ipHash]);
  $pdo->beginTransaction();
  $guard=$pdo->prepare('SELECT failures,locked_until FROM reservation_lookup_attempts WHERE ip_hash=? FOR UPDATE'); $guard->execute([$ipHash]); $attempt=$guard->fetch();
  if($attempt['locked_until'] && strtotime($attempt['locked_until'])>time()) { $retry=max(1,strtotime($attempt['locked_until'])-time()); $pdo->commit(); json_response(['ok'=>false,'reason'=>'TOO_MANY_ATTEMPTS','retryAfterSeconds'=>$retry],429); }
  $q=$pdo->prepare('SELECT * FROM reservations WHERE reservation_code=? LIMIT 1'); $q->execute([$code]); $r=$q->fetch();
  if(!$r || !password_verify($pin,$r['booking_pin_hash'])) {
    $fail=(int)$attempt['failures']+1; $until=$fail>=5?date('Y-m-d H:i:s',time()+120):null; if($until)$fail=0;
    $u=$pdo->prepare('UPDATE reservation_lookup_attempts SET failures=?,locked_until=? WHERE ip_hash=?'); $u->execute([$fail,$until,$ipHash]); $pdo->commit();
    json_response(['ok'=>false,'reason'=>'NOT_FOUND'],404);
  }
  $pdo->prepare('UPDATE reservation_lookup_attempts SET failures=0,locked_until=NULL WHERE ip_hash=?')->execute([$ipHash]); $pdo->commit();
  json_response(['ok'=>true,'reservation'=>['reservationId'=>$r['reservation_code'],'status'=>$r['status'],'submittedAt'=>$r['created_at'],'customerName'=>$r['customer_name'],'contactPerson'=>$r['customer_name'],'contactNumberMasked'=>substr($r['contact_number'],0,2).'*******'.substr($r['contact_number'],-2),'emailMasked'=>substr($r['email'],0,1).'***'.substr($r['email'],strpos($r['email'],'@')),'eventDate'=>$r['event_date'],'eventTime'=>substr($r['event_time'],0,5),'eventType'=>$r['event_type'],'guests'=>(int)$r['guests'],'mainService'=>$r['main_service'],'selectedPackage'=>$r['selected_package'],'mainCourses'=>json_decode($r['main_courses']??'[]',true)?:[],'additionalServices'=>json_decode($r['additional_services'],true)?:[],'estimatedTotal'=>(float)$r['estimated_total']]]);
}
json_response(['ok'=>false,'reason'=>'NOT_FOUND'],404);
