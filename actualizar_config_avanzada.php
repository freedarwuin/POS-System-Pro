<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Admin') {
    header("Location: ventas.php");
    exit();
}
include 'conexion.php';
$usuario_actual = $_SESSION['usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seccion = $_POST['seccion'] ?? '';

    if ($seccion === 'finanzas') {
        $tasa_dolar = floatval($_POST['tasa_dolar']);
        $usar_iva = intval($_POST['usar_iva']);
        $impuesto_iva = floatval($_POST['impuesto_iva']);
        $usar_igtf = intval($_POST['usar_igtf']);
        $impuesto_igtf = floatval($_POST['impuesto_igtf']);

        // 1. Conseguir valores anteriores para registrar con precisión qué cambió
        $antiguo_q = mysqli_query($conexion, "SELECT tasa_dolar, impuesto_iva FROM configuracion WHERE id = 1");
        $antiguo = mysqli_fetch_assoc($antiguo_q);

        // 2. Actualizar configuración
        $update = "UPDATE configuracion SET 
                    tasa_dolar = '$tasa_dolar', 
                    usar_iva = '$usar_iva', 
                    impuesto_iva = '$impuesto_iva', 
                    usar_igtf = '$usar_igtf', 
                    impuesto_igtf = '$impuesto_igtf' 
                   WHERE id = 1";
        
        if (mysqli_query($conexion, $update)) {
            // 3. Escribir en la Bitácora de Auditoría
            $detalles = "Tasa cambiada de {$antiguo['tasa_dolar']} a {$tasa_dolar}. IVA nuevo: {$impuesto_iva}% (Activo: {$usar_iva}). IGTF: {$impuesto_igtf}% (Activo: {$usar_igtf}).";
            mysqli_query($conexion, "INSERT INTO auditoria (usuario, accion, detalles) VALUES ('$usuario_actual', 'CAMBIO PARAMETROS FINANCIEROS', '$detalles')");
        }
    } 
    
    // ... dentro del archivo actualizar_config_avanzada.php, buscar el bloque de telegram y cambiarlo por este:

else if ($seccion === 'whatsapp') {
    $whatsapp_telefono = mysqli_real_escape_string($conexion, $_POST['whatsapp_telefono']);
    $whatsapp_api_key = mysqli_real_escape_string($conexion, $_POST['whatsapp_api_key']);

    $update = "UPDATE configuracion SET 
                whatsapp_telefono = '$whatsapp_telefono', 
                whatsapp_api_key = '$whatsapp_api_key' 
               WHERE id = 1";
    
    if (mysqli_query($conexion, $update)) {
        $detalles = "Se reconfiguró el número de WhatsApp receptor ({$whatsapp_telefono}) para el envío automático de cortes de caja.";
        mysqli_query($conexion, "INSERT INTO auditoria (usuario, accion, detalles) VALUES ('$usuario_actual', 'ACTUALIZACIÓN WHATSAPP', '$detalles')");
    }
}

    header("Location: configuracion.php?status=success");
    exit();
}
?>