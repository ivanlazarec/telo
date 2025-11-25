<?php
require __DIR__ . '/vendor/autoload.php';

MercadoPago\SDK::setAccessToken("APP_USR-4518252275853191-112421-370721fbc465852fcb25cc7cba42e681-59176727");



// ==== Conexión a la base (misma que api-reservar.php) ====
$servername = "127.0.0.1";
$username   = "u460517132_F5bOi";
$password   = "mDjVQbpI5A";
$dbname     = "u460517132_GxbHQ";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión']);
    exit;
}

// ==== Funciones de fecha/hora iguales a tu API ====
function nowArgDT(){ return new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires')); }
function argNowInfo(){ $dt=nowArgDT(); return [(int)$dt->format('w'), (int)$dt->format('G')]; } // 0=Dom..6=Sab

header('Content-Type: application/json');

// ==== Datos que vienen del formulario ====
$tipoSeleccionado  = $_POST['tipo']  ?? '';
$turnoSeleccionado = $_POST['turno'] ?? '';

if(!$tipoSeleccionado || !$turnoSeleccionado){
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

// ==== Calcular turno de precio igual que en api-reservar.php ====
// (2h / 3h / noche / noche-finde)
list($dow,$hour) = argNowInfo();

// si el usuario elige "noche" pero hoy es viernes o sábado, usamos "noche-finde"
if ($turnoSeleccionado === 'noche' && in_array($dow, [5,6])) {
    $turnoBD = 'noche-finde';
} else {
    $turnoMap = [
        '2h'    => 'turno-2h',
        '3h'    => 'turno-3h',
        'noche' => 'noche'
    ];
    $turnoBD = $turnoMap[$turnoSeleccionado] ?? $turnoSeleccionado;
}

// ==== Buscar precio en precios_habitaciones ====
$stmt = $conn->prepare("SELECT precio FROM precios_habitaciones WHERE tipo=? AND turno=?");
$stmt->bind_param("ss", $tipoSeleccionado, $turnoBD);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$precio = floatval($res['precio'] ?? 0);

if($precio <= 0){
    echo json_encode(['error' => 'No se encontró precio para esa combinación']);
    exit;
}

// ==== Crear preferencia de MP ====
$preference = new MercadoPago\Preference();

$item = new MercadoPago\Item();
$item->title = "Reserva: $tipoSeleccionado ($turnoSeleccionado)";
$item->quantity = 1;
$item->unit_price = $precio;

$preference->items = [$item];

// Guardamos los datos del turno en metadata
$preference->metadata = [
    "tipo"  => $tipoSeleccionado,
    "turno" => $turnoSeleccionado
];

// Donde vuelve el usuario luego del pago
$preference->back_urls = [
    "success" => "https://lamoradatandil.com/gracias.php",
    "pending" => "https://lamoradatandil.com/gracias.php",
    "failure" => "https://lamoradatandil.com/gracias.php"
];
$preference->auto_return = "approved";

// Webhook para confirmar pagos
$preference->notification_url = "https://lamoradatandil.com/mp-webhook.php";

$preference->save();

echo json_encode([
    'init_point' => $preference->init_point
]);
