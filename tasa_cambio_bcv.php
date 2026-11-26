<?php
session_start();
if (empty($_SESSION['usuario']) || (($_SESSION['rol'] ?? '') !== 'Admin')) {
    header('Location: ventas.php');
    exit();
}
if (empty($_SESSION['csrf_tasa_bcv'])) {
    $_SESSION['csrf_tasa_bcv'] = bin2hex(random_bytes(32));
}
require __DIR__ . '/conexion.php';
$resultado = mysqli_query($conexion, 'SELECT tasa_dolar FROM configuracion WHERE id = 1');
$configuracion = $resultado ? mysqli_fetch_assoc($resultado) : null;
$tasaActual = (float) ($configuracion['tasa_dolar'] ?? 0);
$csrf = htmlspecialchars($_SESSION['csrf_tasa_bcv'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tasa BCV | POS System Pro</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f4f6fb;color:#243047;margin:0;padding:24px}
        main{max-width:640px;margin:6vh auto;background:#fff;padding:30px;border-radius:18px;box-shadow:0 10px 35px #15233b12}
        h1{margin-top:0;font-size:1.5rem}.rate{font-size:2rem;font-weight:bold;color:#d81b60;margin:12px 0}
        button{border:0;border-radius:10px;background:#d81b60;color:#fff;padding:13px 18px;font-weight:bold;cursor:pointer}
        button:disabled{opacity:.6;cursor:wait}.muted{color:#64748b;font-size:.92rem;line-height:1.5}
        #mensaje{margin-top:16px;padding:12px;border-radius:9px;display:none}#mensaje.ok{display:block;background:#ecfdf5;color:#065f46}#mensaje.error{display:block;background:#fef2f2;color:#991b1b}
    </style>
</head>
<body>
<main>
    <h1>Actualizar tasa de cambio USD</h1>
    <p class="muted">La tasa actual guardada en el sistema es:</p>
    <div class="rate" id="tasa-actual">Bs. <?php echo htmlspecialchars(number_format($tasaActual, 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?></div>
    <p class="muted">Al actualizar, se consulta el valor publicado por el Banco Central de Venezuela y se guarda como tasa operativa. Si el BCV no está disponible, la tasa existente no se modifica.</p>
    <button type="button" id="actualizar">Consultar BCV y actualizar</button>
    <div id="mensaje" role="status" aria-live="polite"></div>
    <p class="muted">Fuente: <a href="https://www.bcv.org.ve/" target="_blank" rel="noopener noreferrer">bcv.org.ve</a></p>
</main>
<script>
(() => {
    const boton = document.getElementById('actualizar');
    const mensaje = document.getElementById('mensaje');
    const tasa = document.getElementById('tasa-actual');
    const csrf = <?php echo json_encode($csrf); ?>;
    boton.addEventListener('click', async () => {
        boton.disabled = true;
        boton.textContent = 'Consultando BCV…';
        mensaje.className = '';
        mensaje.style.display = 'none';
        try {
            const respuesta = await fetch('api_tasa_bcv.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'X-CSRF-Token': csrf, 'Accept': 'application/json'}
            });
            const datos = await respuesta.json();
            if (!respuesta.ok || !datos.ok) throw new Error(datos.mensaje || 'No se pudo actualizar la tasa.');
            tasa.textContent = 'Bs. ' + Number(datos.tasa).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 8});
            mensaje.textContent = datos.mensaje + ' Valor BCV: ' + Number(datos.tasa).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 8}) + ' Bs/USD.';
            mensaje.className = 'ok';
        } catch (error) {
            mensaje.textContent = error.message || 'Error de conexión. Intenta nuevamente.';
            mensaje.className = 'error';
        } finally {
            boton.disabled = false;
            boton.textContent = 'Consultar BCV y actualizar';
        }
    });
})();
</script>
</body>
</html>
