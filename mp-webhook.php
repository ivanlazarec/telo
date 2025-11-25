<?php
file_put_contents(__DIR__."/webhook-log.txt", date("Y-m-d H:i:s")." - Webhook hit: ".file_get_contents("php://input")."\n", FILE_APPEND);

require __DIR__ . '/vendor/autoload.php';

MercadoPago\SDK::setAccessToken("APP_USR-4518252275853191-112421-370721fbc465852fcb25cc7cba42e681-59176727");


// ==== Recibir notificación ====
$body = file_get_contents("php://input");
$data = json_decode($body, true);

if(!isset($data['data']['id'])){
    http_response_code(200);
    exit;
}

$payment_id = $data['data']['id'];
$payment = MercadoPago\Payment::find_by_id($payment_id);

// Solo procesamos pagos aprobados
if($payment->status !== "approved"){
    http_response_code(200);
    exit;
}

// ==== Sacar tipo y turno desde metadata ====
$tipoSeleccionado  = $payment->metadata->tipo ?? '';
$turnoSeleccionado = $payment->metadata->turno ?? '';

if(!$tipoSeleccionado || !$turnoSeleccionado){
    http_response_code(200);
    exit;
}

// ==== Conexión DB (misma que tu API) ====
$servername = "127.0.0.1";
$username   = "u460517132_F5bOi";
$password   = "mDjVQbpI5A";
$dbname     = "u460517132_GxbHQ";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    exit;
}

// ==== Config y funciones copiadas de tu API ====
$SUPER_VIP = [20,21];
$VIP_LIST  = [3,4,11,12,13,28,29,30,37,38];

function nowArgDT(){ return new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires')); }
function nowUTCStrFromArg(){ $dt=nowArgDT(); $dt->setTimezone(new DateTimeZone('UTC')); return $dt->format('Y-m-d H:i:s'); }
function argDateToday(){ return nowArgDT()->format('Y-m-d'); }
function argNowInfo(){ $dt=nowArgDT(); return [(int)$dt->format('w'), (int)$dt->format('G')]; }

function tipoDeHabitacion($id,$SUPER_VIP,$VIP_LIST){
  if(in_array($id,$SUPER_VIP)) return 'Super VIP';
  if(in_array($id,$VIP_LIST))  return 'VIP';
  return 'Común';
}

// ==== Determinar turnoTag igual que tu API ====
list($dow,$hour)=argNowInfo();

if ($turnoSeleccionado === 'noche') {
    // noche de finde
    $turnoTag = in_array($dow, [5,6]) ? 'noche-finde' : 'noche';
} else {
    // 2h o 3h según día
    $turnoTag = in_array($dow, [0,5,6]) ? 'turno-2h' : 'turno-3h';
}

$startUTC = nowUTCStrFromArg();
$fecha    = argDateToday();

// ==== Buscar habitación libre igual que tu API ====
if($tipoSeleccionado==='Común'){
  $ids = range(1,40);
  $ids = array_diff($ids, $VIP_LIST, $SUPER_VIP);
} elseif($tipoSeleccionado==='VIP'){
  $ids = $VIP_LIST;
} else {
  $ids = $SUPER_VIP;
}

// Priorizar nuevas (de 11 a 20 y 30 a 21)
$prioridad = array_merge(range(11,20), range(30,21));
$ordenadas = array_unique(array_merge($prioridad, $ids));

$habitacionId = null;
foreach($ordenadas as $id){
  $q = $conn->prepare("SELECT estado FROM habitaciones WHERE id=?");
  $q->bind_param('i',$id);
  $q->execute();
  $r = $q->get_result()->fetch_assoc();
  if($r && $r['estado']==='libre'){
    $habitacionId = $id;
    break;
  }
}

if(!$habitacionId){
    // si no hay habitación, simplemente no hacemos reserva
    http_response_code(200);
    exit;
}

// ==== Marcar como reservada ====
$st = $conn->prepare("UPDATE habitaciones SET estado='reservada', tipo_turno=?, hora_inicio=? WHERE id=?");
$st->bind_param('ssi', $turnoTag, $startUTC, $habitacionId);
$st->execute();
$st->close();

// ==== Generar código EXACTO igual que tu API ====
$codigo = strtoupper(substr(md5(uniqid('', true)), 0, 4));
$tipo   = tipoDeHabitacion($habitacionId, $SUPER_VIP, $VIP_LIST);
$estado = 'reservada';

// ==== Insertar en historial_habitaciones igual que tu API ====
$ins = $conn->prepare("INSERT INTO historial_habitaciones (habitacion,codigo,tipo,estado,turno,hora_inicio,fecha_registro) VALUES (?,?,?,?,?,?,?)");
$ins->bind_param('issssss', $habitacionId, $codigo, $tipo, $estado, $turnoTag, $startUTC, $fecha);
$ins->execute();
$ins->close();

// ==== Vincular payment_id con código para gracias.php ====
$conn->query("INSERT INTO pagos_mp (payment_id, codigo) VALUES ('".$conn->real_escape_string($payment_id)."', '".$conn->real_escape_string($codigo)."')");

http_response_code(200);
