<?php
require_once __DIR__ . '/../includes/admin_auth.php';
ini_set('display_errors', '0');
set_exception_handler(static function (Throwable $e): void { json_response(['ok'=>false,'reason'=>'NETWORK'],500); });
if ($_SERVER['REQUEST_METHOD'] !== 'GET') require_csrf();
try { $pdo=db(); } catch(Throwable $e) { json_response(['ok'=>false,'reason'=>'NETWORK'],503); }
$action=$_GET['action']??'';
if($action==='list' && $_SERVER['REQUEST_METHOD']==='GET') {
 $rows=$pdo->query('SELECT reservation_code,customer_name,contact_number,email,event_date,event_time,guests,main_service,selected_package,estimated_total,status FROM reservations ORDER BY created_at DESC')->fetchAll();
 json_response(['ok'=>true,'reservations'=>$rows]);
}
if($action==='status' && $_SERVER['REQUEST_METHOD']==='POST') {
 $d=request_json(); $code=(string)($d['reservationId']??''); $status=(string)($d['status']??'');
 if(!in_array($status,['Confirmed','Rejected','Cancelled'],true)) json_response(['ok'=>false,'reason'=>'INVALID'],400);
 try { $pdo->beginTransaction(); $q=$pdo->prepare('SELECT id,status FROM reservations WHERE reservation_code=? FOR UPDATE'); $q->execute([$code]); $r=$q->fetch();
   if(!$r) { $pdo->rollBack(); json_response(['ok'=>false,'reason'=>'NOT_FOUND'],404); }
   if($r['status']!=='Pending' && !($r['status']==='Confirmed' && $status==='Cancelled') && $r['status']!==$status) { $pdo->rollBack(); json_response(['ok'=>false,'reason'=>'INVALID_TRANSITION'],409); }
   $u=$pdo->prepare('UPDATE reservations SET status=?,updated_by=? WHERE id=?'); $u->execute([$status,$_SESSION['admin_id'],$r['id']]);
   if($r['status']!==$status) { $h=$pdo->prepare('INSERT INTO reservation_status_history (reservation_id,old_status,new_status,changed_by) VALUES (?,?,?,?)'); $h->execute([$r['id'],$r['status'],$status,$_SESSION['admin_id']]); } $pdo->commit();
   json_response(['ok'=>true,'reservationId'=>$code,'status'=>$status]);
 } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); json_response(['ok'=>false,'reason'=>'NETWORK'],500); }
}
if($action==='edit' && $_SERVER['REQUEST_METHOD']==='POST') {
 $d=request_json(); $code=(string)($d['reservationId']??''); $date=(string)($d['eventDate']??''); $time=(string)($d['eventTime']??''); $guests=filter_var($d['guests']??null,FILTER_VALIDATE_INT); $status=(string)($d['status']??'');
 $dateObj=DateTimeImmutable::createFromFormat('!Y-m-d',$date); $validStatus=['Pending','Confirmed','Rejected','Cancelled'];
 if(!$dateObj || $dateObj->format('Y-m-d')!==$date || $date<(new DateTimeImmutable('today',new DateTimeZone('Asia/Manila')))->format('Y-m-d') || !preg_match('/^(?:0[8-9]|1[0-9]|2[01]):00$/',$time) || !$guests || $guests<1 || $guests>500 || !in_array($status,$validStatus,true)) json_response(['ok'=>false,'reason'=>'INVALID'],400);
 try { $pdo->beginTransaction(); $q=$pdo->prepare('SELECT id,status FROM reservations WHERE reservation_code=? FOR UPDATE'); $q->execute([$code]); $r=$q->fetch();
   if(!$r) { $pdo->rollBack(); json_response(['ok'=>false,'reason'=>'NOT_FOUND'],404); }
   if($r['status']!=='Pending' && !($r['status']==='Confirmed' && $status==='Cancelled') && $r['status']!==$status) { $pdo->rollBack(); json_response(['ok'=>false,'reason'=>'INVALID_TRANSITION'],409); }
   if(in_array($status,['Pending','Confirmed'],true)) { $c=$pdo->prepare("SELECT id FROM reservations WHERE event_date=? AND status IN ('Pending','Confirmed') AND id<>? FOR UPDATE"); $c->execute([$date,$r['id']]); if($c->fetch()) { $pdo->rollBack(); json_response(['ok'=>false,'reason'=>'DATE_TAKEN'],409); } }
   $u=$pdo->prepare('UPDATE reservations SET event_date=?,event_time=?,guests=?,status=?,updated_by=? WHERE id=?'); $u->execute([$date,$time,$guests,$status,$_SESSION['admin_id'],$r['id']]);
   if($r['status']!==$status) { $h=$pdo->prepare('INSERT INTO reservation_status_history (reservation_id,old_status,new_status,changed_by) VALUES (?,?,?,?)'); $h->execute([$r['id'],$r['status'],$status,$_SESSION['admin_id']]); }
   $pdo->commit(); json_response(['ok'=>true,'reservationId'=>$code,'status'=>$status]);
 } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); json_response(['ok'=>false,'reason'=>'NETWORK'],500); }
}
json_response(['ok'=>false,'reason'=>'NOT_FOUND'],404);
