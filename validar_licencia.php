<?php
// Validación del periodo demo y de licencias anuales mediante Supabase RPC.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conexion)) {
    require_once __DIR__ . '/conexion.php';
}
require_once __DIR__ . '/supabase/license_client.php';

$esSolicitudActivacion = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activar_licencia']);
if ($esSolicitudActivacion) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $installation = posLicenseInstallationId($conexion);
        $key = trim((string) (isset($_POST['key_cliente']) ? $_POST['key_cliente'] : ''));
        $license = posSupabaseLicenseRpc('pos_activate_annual_license', array(
            'p_installation_id' => $installation['installation_id'],
            'p_activation_key' => $key
        ));

        if (isset($license['status']) && $license['status'] === 'active') {
            posSyncLocalLicense($conexion, $installation, $license);
            echo json_encode(array(
                'status' => 'success',
                'message' => 'Licencia anual activa hasta el ' . date('d/m/Y', strtotime($license['expires_at']))
            ));
        } else {
            echo json_encode(array(
                'status' => 'error',
                'message' => isset($license['message']) ? $license['message'] : 'La clave no pudo ser activada.'
            ));
        }
    } catch (Throwable $e) {
        error_log('[licencia] ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(array(
            'status' => 'error',
            'message' => 'No se pudo conectar con el servicio de licencias. Intente nuevamente más tarde.'
        ));
    }
    exit();
}

$bloqueado = false;
$mensajeError = '';
$licenciaActual = null;

try {
    $installation = posLicenseInstallationId($conexion);
    $licenciaActual = posSupabaseLicenseRpc('pos_license_status', array(
        'p_installation_id' => $installation['installation_id']
    ));
    posSyncLocalLicense($conexion, $installation, $licenciaActual);

    if (!isset($licenciaActual['status']) || $licenciaActual['status'] !== 'active') {
        $bloqueado = true;
        if (isset($licenciaActual['status']) && $licenciaActual['status'] === 'suspended') {
            $mensajeError = 'La licencia de esta instalación fue suspendida. Comuníquese con el proveedor para regularizarla.';
        } else {
            $mensajeError = 'El periodo demo de 15 días finalizó. Ingrese una licencia anual para continuar utilizando el sistema.';
        }
    }
} catch (Throwable $e) {
    error_log('[licencia] ' . $e->getMessage());
    $bloqueado = true;
    $mensajeError = 'No se pudo verificar la licencia en línea. Revise la conexión a Internet y la configuración del servicio de licencias.';
}

$paginaActual = basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
if ($bloqueado && $paginaActual !== 'licencia_bloqueada.php') {
    header('Location: licencia_bloqueada.php?msj=' . urlencode($mensajeError));
    exit();
}
?>
