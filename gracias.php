<?php
// Conexión igual que tu sistema
$servername = "127.0.0.1";
$username   = "u460517132_F5bOi";
$password   = "mDjVQbpI5A";
$dbname     = "u460517132_GxbHQ";

$conn = new mysqli($servername, $username, $password, $dbname);

// Si no viene payment_id no podemos mostrar código
if(!isset($_GET['payment_id'])){
    echo "<h1>Pago procesado</h1><p>No pudimos identificar tu reserva.</p>";
    exit;
}

$payment_id = $conn->real_escape_string($_GET['payment_id']);

// Buscar código asociado al payment_id en pagos_mp
$q = $conn->query("SELECT codigo FROM pagos_mp WHERE payment_id='$payment_id' LIMIT 1");

if($q->num_rows == 0){
    // Mercado Pago a veces tarda unos segundos en enviar el webhook
    echo "<h1>Procesando pago...</h1>";
    echo "<p>Tu pago fue aprobado pero la reserva todavía se está registrando en el sistema.</p>";
    echo "<p>Actualizá esta página en unos segundos.</p>";
    exit;
}

$codigo = $q->fetch_assoc()['codigo'];

// Mostrar código de reserva real
echo "<h1>¡Reserva confirmada!</h1>";
echo "<p>Tu código es:</p>";
echo "<h2 style='font-size:32px;color:#0B5FFF;'>$codigo</h2>";
echo "<p>Guardalo. Lo vas a usar para ingresar, tenes que comunicarlo en el portero. El reloj del turno ya está corriendo.</p>";
