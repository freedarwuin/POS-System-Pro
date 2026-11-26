<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Admin') {
    header('Location: ventas.php');
    exit();
}
include 'conexion.php';
$usuario_actual = $_SESSION['usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seccion = $_POST['seccion'] ?? '';

    if ($seccion === 'finanzas') {
        $tasa_dolar = (float) str_replace(',', '.', (string) ($_POST['tasa_dolar'] ?? '0'));
        $tasa_automatica = (int) ($_POST['tasa_automatica'] ?? 0);
        $usar_iva = (int) ($_POST['usar_iva'] ?? 0);
        $impuesto_iva = (float) ($_POST['impuesto_iva'] ?? 0);
        $usar_igtf = (int) ($_POST['usar_igtf'] ?? 0);
        $impuesto_igtf = (float) ($_POST['impuesto_igtf'] ?? 0);

        if ($tasa_dolar <= 0) {
            header('Location: configuracion.php?status=tasa_invalida');
            exit();
        }

        $antiguo_q = mysqli_query($conexion, 'SELECT tasa_dolar, tasa_manual, tasa_automatica, impuesto_iva FROM configuracion WHERE id = 1');
        $antiguo = mysqli_fetch_assoc($antiguo_q);

        mysqli_begin_transaction($conexion);

        if ($tasa_automatica === 1) {
            $tasa_dolar_api = (float) ($antiguo['tasa_manual'] ?? 0);
            $update = mysqli_prepare($conexion, 'UPDATE configuracion SET tasa_automatica = 1, tasa_manual = ?, tasa_dolar = ?, usar_iva = ?, impuesto_iva = ?, usar_igtf = ?, impuesto_igtf = ? WHERE id = 1');
            mysqli_stmt_bind_param($update, 'ddiiii', $tasa_dolar, $tasa_dolar, $usar_iva, $impuesto_iva, $usar_igtf, $impuesto_igtf);
        } else {
            $update = mysqli_prepare($conexion, 'UPDATE configuracion SET tasa_dolar = ?, tasa_manual = ?, tasa_automatica = 0, usar_iva = ?, impuesto_iva = ?, usar_igtf = ?, impuesto_igtf = ? WHERE id = 1');
            mysqli_stmt_bind_param($update, 'ddiiii', $tasa_dolar, $tasa_dolar, $usar_iva, $impuesto_iva, $usar_igtf, $impuesto_igtf);
        }

        if (!$update || !mysqli_stmt_execute($update)) {
            mysqli_rollback($conexion);
            header('Location: configuracion.php?status=db_error');
            exit();
        }
        mysqli_stmt_close($update);

        $modo = $tasa_automatica === 1 ? 'automática' : 'manual';
        $detalles = sprintf('Tasa cambiada de %s a %s. Modo: %s. IVA: %s%% (Activo: %d). IGTF: %s%% (Activo: %d).',
            $antiguo['tasa_dolar'], $tasa_dolar, $modo, $impuesto_iva, $usar_iva, $impuesto_igtf, $usar_igtf);
        $auditoria = mysqli_prepare($conexion, 'INSERT INTO auditoria (usuario, accion, detalles) VALUES (?, ?, ?)');
        $accion = 'CAMBIO PARAMETROS FINANCIEROS';
        mysqli_stmt_bind_param($auditoria, 'sss', $usuario_actual, $accion, $detalles);
        mysqli_stmt_execute($auditoria);
        mysqli_stmt_close($auditoria);

        mysqli_commit($conexion);
    } elseif ($seccion === 'whatsapp') {
        $whatsapp_telefono = mysqli_real_escape_string($conexion, $_POST['whatsapp_telefono']);
        $whatsapp_api_key = mysqli_real_escape_string($conexion, $_POST['whatsapp_api_key']);

        $update = "UPDATE configuracion SET whatsapp_telefono = '$whatsapp_telefono', whatsapp_api_key = '$whatsapp_api_key' WHERE id = 1";

        if (mysqli_query($conexion, $update)) {
            $detalles = "Se reconfiguró el número de WhatsApp receptor ({$whatsapp_telefono}) para el envío automático de cortes de caja.";
            mysqli_query($conexion, "INSERT INTO auditoria (usuario, accion, detalles) VALUES ('$usuario_actual', 'ACTUALIZACIÓN WHATSAPP', '$detalles')");
        }
    }

    header('Location: configuracion.php?status=success');
    exit();
}
