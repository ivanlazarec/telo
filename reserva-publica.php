<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reserva tu habitación</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:Inter,Arial,sans-serif;background:#f9fafb;margin:0;padding:0;text-align:center;}
form{background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:400px;margin:40px auto;}
h1{color:#0B5FFF;}
select,button{width:100%;padding:10px;margin:6px 0;border-radius:8px;border:1px solid #ddd;font-size:16px;}
#status-indicator{margin:20px auto;font-weight:700;}
.status-ok{color:green;} .status-bad{color:red;}
</style>
</head>
<body>
<h1>Reserva aquí</h1>
<div id="status-indicator">Verificando disponibilidad...</div>
<form id="reservaForm">
  <label>Tipo de habitación:</label>
  <select name="tipo" id="tipo">
    <option value="Común">Común</option>
    <option value="VIP">VIP</option>
    <option value="Super VIP">Super VIP</option>
  </select>

  <label>Turno:</label>
  <select name="turno" id="turno">
    <option value="2h">Turno 2 horas</option>
    <option value="3h">Turno 3 horas</option>
    <option value="noche">Noche (21:00–10:00)</option>
  </select>
  <div id="precio-box" style="margin-top:10px;font-weight:700;color:#0B5FFF;font-size:16px;">
    💰 Precio del turno: <span id="precio-valor">$0</span>
  </div>

  <button type="submit">Reservar</button>
</form>

<div id="resultado"></div>

<script>
function nowArg(){
  const now = new Date();
  const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
  return new Date(utc - 3 * 3600 * 1000); // hora Argentina (UTC-3)
}

function turnoDisponible(){
  const d = nowArg();
  const dow = d.getDay(); // 0=Dom, 5=Vie, 6=Sab
  const h = d.getHours();
  const turnoSelect = document.getElementById('turno');
  turnoSelect.innerHTML = ''; // limpiar opciones

  // Definir horas de turno corto o largo según el día
  const hrs = ([0,5,6].includes(dow) ? 2 : 3);
  // Mostrar siempre turno normal
  turnoSelect.innerHTML += `<option value="${hrs}h">Turno ${hrs} horas</option>`;

  // Agregar también la noche si es >=21h o <10h
  if (h >= 21 || h < 10) {
    turnoSelect.innerHTML += '<option value="noche">Noche (21:00–10:00)</option>';
  }
}

turnoDisponible();
setInterval(turnoDisponible, 60000);

// ==== Verificar disponibilidad cada 5s ====
async function checkDisponibilidad(){
  const ind = document.getElementById('status-indicator');
  try{
    const r = await fetch('estado_hotel.php?'+Date.now());
    const data = await r.json();
    const libres = data.filter(x=>x.estado==='libre').length;
    if(libres>0){
      ind.textContent='✅ TENEMOS DISPONIBILIDAD';
      ind.className='status-ok';
    } else {
      ind.textContent='❌ NO TENEMOS HABITACIONES';
      ind.className='status-bad';
    }
  }catch(e){
    ind.textContent='Error verificando';
    ind.className='status-bad';
  }
}
checkDisponibilidad();
setInterval(checkDisponibilidad,5000);


// ==== Enviar a Mercado Pago ====
document.getElementById('reservaForm').addEventListener('submit', async e => {
  e.preventDefault();

  const tipo  = document.getElementById('tipo').value;
  const turno = document.getElementById('turno').value;

  const resultadoDiv = document.getElementById('resultado');
  resultadoDiv.innerHTML = '';

  try {
    const r = await fetch('crear-preferencia.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ tipo, turno })
    });

    const j = await r.json();

    if (j.init_point) {
      // Redirigir al checkout de Mercado Pago
      window.location.href = j.init_point;
    } else {
      resultadoDiv.innerHTML = `<p style="color:red">Error: ${j.error || 'No se pudo crear el pago.'}</p>`;
    }
  } catch (err) {
    resultadoDiv.innerHTML = `<p style="color:red">Error de comunicación con el servidor.</p>`;
  }
});

// ==== Actualizar precio dinámico ====
document.addEventListener('DOMContentLoaded', ()=>{
  const tipo = document.getElementById('tipo');
  const turno = document.getElementById('turno');
  const precioSpan = document.getElementById('precio-valor');

  async function actualizarPrecio(){
    const t = tipo.value, u = turno.value;
    if(!t || !u) { precioSpan.textContent = '$0'; return; }
    try{
      const res = await fetch('api-reservar.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({get_precio:1, tipo:t, turno:u})
      });
      const data = await res.json();
      const precio = parseFloat(data.precio||0);
      precioSpan.textContent = precio>0 ? `$${precio.toLocaleString('es-AR')}` : '$0';
    }catch(e){
      precioSpan.textContent = '$0';
    }
  }

  tipo.addEventListener('change', actualizarPrecio);
  turno.addEventListener('change', actualizarPrecio);
  actualizarPrecio(); // ejecuta al cargar
});

</script>



</body>
</html>
