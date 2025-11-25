<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

/*==================== Conexión ====================*/
$servername = "127.0.0.1";
$username   = "u460517132_F5bOi";
$password   = "mDjVQbpI5A";
$dbname     = "u460517132_GxbHQ";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { 
  http_response_code(500); 
  echo json_encode(['error' => 'Error de conexión a la base de datos']);
  exit;
}

/* ====== CONSULTA DE PRECIO SEGÚN TIPO Y TURNO ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_precio'])) {
    $tipo = $_POST['tipo'] ?? '';
    $turno = $_POST['turno'] ?? '';
list($dow,$hour) = argNowInfo();

// si el usuario elige "noche" pero hoy es viernes o sábado, mostramos el precio de "noche-finde"
if ($turno === 'noche' && in_array($dow, [5,6])) {
    $turno = 'noche-finde';
}


    $turnoMap = [
  '2h' => 'turno-2h',
  '3h' => 'turno-3h',
  'noche' => 'noche'
];
$turnoBD = $turnoMap[$turno] ?? $turno;

$stmt = $conn->prepare("SELECT precio FROM precios_habitaciones WHERE tipo=? AND turno=?");
$stmt->bind_param("ss", $tipo, $turnoBD);

    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    echo json_encode(['precio' => $res['precio'] ?? 0]);
    $stmt->close();
    $conn->close();
    exit; // 🚨 Detiene la ejecución aquí, no sigue con la lógica de reserva
}

/*=================== Config ===================*/
$SUPER_VIP = [20,21];
$VIP_LIST  = [3,4,11,12,13,28,29,30,37,38];

function tipoDeHabitacion($id,$SUPER_VIP,$VIP_LIST){
  if(in_array($id,$SUPER_VIP)) return 'Super VIP';
  if(in_array($id,$VIP_LIST))  return 'VIP';
  return 'Común';
}

function nowArgDT(){ return new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires')); }
function nowUTCStrFromArg(){ $dt=nowArgDT(); $dt->setTimezone(new DateTimeZone('UTC')); return $dt->format('Y-m-d H:i:s'); }
function argDateToday(){ return nowArgDT()->format('Y-m-d'); }
function argNowInfo(){ $dt=nowArgDT(); return [(int)$dt->format('w'), (int)$dt->format('G')]; } // 0=Dom..6=Sab
function nightEndTsFromStartArg($startTs){ $dt=new DateTime('@'.$startTs); $dt->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires')); if((int)$dt->format('G')>=21){$dt->modify('+1 day');} $dt->setTime(10,0,0); return $dt->getTimestamp(); }

/*=================== Lógica ===================*/
$tipoSeleccionado = $_POST['tipo'] ?? '';
$turnoSeleccionado = $_POST['turno'] ?? '';

if(!$tipoSeleccionado || !$turnoSeleccionado){
  echo json_encode(['success'=>false,'error'=>'Datos incompletos']);
  exit;
}

// Turno y duración
list($dow,$hour)=argNowInfo();
// Detectar noche de fin de semana
if ($turnoSeleccionado === 'noche') {
    // si hoy es viernes (5) o sábado (6), usamos noche-finde
    $turnoTag = in_array($dow, [5,6]) ? 'noche-finde' : 'noche';
} else {
    $turnoTag = in_array($dow, [0,5,6]) ? 'turno-2h' : 'turno-3h';
}
$blockHours = ($turnoTag==='turno-2h') ? 2 : (($turnoTag==='turno-3h') ? 3 : 11);
$startUTC = nowUTCStrFromArg();
$fecha = argDateToday();

/*=================== Buscar habitación disponible ===================*/
$whereTipo = '';
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
  echo json_encode(['success'=>false,'error'=>'No hay habitaciones disponibles']);
  exit;
}

/*=================== Marcar como reservada ===================*/
$st = $conn->prepare("UPDATE habitaciones SET estado='reservada', tipo_turno=?, hora_inicio=? WHERE id=?");
$st->bind_param('ssi', $turnoTag, $startUTC, $habitacionId);
$st->execute();
$st->close();

/*=================== Insertar registro ===================*/
$codigo = strtoupper(substr(md5(uniqid('', true)), 0, 4));
$tipo = tipoDeHabitacion($habitacionId, $SUPER_VIP, $VIP_LIST);
$estado = 'reservada';

$ins = $conn->prepare("INSERT INTO historial_habitaciones (habitacion,codigo,tipo,estado,turno,hora_inicio,fecha_registro) VALUES (?,?,?,?,?,?,?)");
$ins->bind_param('issssss', $habitacionId, $codigo, $tipo, $estado, $turnoTag, $startUTC, $fecha);
$ins->execute();
$ins->close();

/*=================== Respuesta ===================*/
echo json_encode([
  'success' => true,
  'habitacion' => $habitacionId,
  'codigo' => $codigo,
  'duracion_horas' => $blockHours
]);
