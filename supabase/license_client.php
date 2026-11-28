<?php
/**
 * Cliente mínimo de la API RPC de Supabase para el servicio de licencias.
 * Define SUPABASE_URL y SUPABASE_PUBLISHABLE_KEY en el entorno del servidor.
 * Se acepta SUPABASE_ANON_KEY como nombre heredado para instalaciones existentes.
 */

function posSupabaseLicenseRpc($function, array $payload)
{
    $baseUrl = rtrim((string) getenv('SUPABASE_URL'), '/');
    $apiKey = (string) getenv('SUPABASE_PUBLISHABLE_KEY');
    if ($apiKey === '') {
        $apiKey = (string) getenv('SUPABASE_ANON_KEY');
    }

    $urlParts = $baseUrl !== '' ? parse_url($baseUrl) : false;
    if ($urlParts === false || empty($urlParts['host']) || strtolower(isset($urlParts['scheme']) ? $urlParts['scheme'] : '') !== 'https'
        || isset($urlParts['user']) || isset($urlParts['pass']) || isset($urlParts['query']) || isset($urlParts['fragment'])) {
        throw new RuntimeException('SUPABASE_URL debe ser una URL HTTPS válida.');
    }
    if ($apiKey === '') {
        throw new RuntimeException('Falta configurar SUPABASE_PUBLISHABLE_KEY.');
    }
    if (substr($apiKey, 0, 10) === 'sb_secret_') {
        throw new RuntimeException('No uses una clave secreta de Supabase en la aplicación PHP. Configura la clave publishable.');
    }
    // Las claves anon antiguas son JWT. Rechaza explícitamente service_role si se proporciona por error.
    $keyParts = explode('.', $apiKey);
    if (count($keyParts) === 3) {
        $payloadPart = strtr($keyParts[1], '-_', '+/');
        $payloadPart .= str_repeat('=', (4 - strlen($payloadPart) % 4) % 4);
        $claims = json_decode(base64_decode($payloadPart), true);
        if (is_array($claims) && isset($claims['role']) && $claims['role'] === 'service_role') {
            throw new RuntimeException('No uses la clave service_role en la aplicación PHP. Configura la clave anon/publishable.');
        }
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('La extensión PHP cURL es necesaria para validar la licencia.');
    }

    $url = $baseUrl . '/rest/v1/rpc/' . rawurlencode($function);
    $body = json_encode($payload);
    if ($body === false) {
        throw new RuntimeException('No se pudo preparar la solicitud de licencia.');
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array(
            'apikey: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ));

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('No fue posible contactar el servicio de licencias: ' . $curlError);
    }

    $data = json_decode($response, true);
    if ($httpStatus < 200 || $httpStatus >= 300 || !is_array($data)) {
        error_log('[licencia] RPC Supabase devolvió HTTP ' . $httpStatus . ': ' . substr((string) $response, 0, 500));
        throw new RuntimeException('El servicio de licencias no pudo validar esta instalación.');
    }

    return $data;
}

function posLicenseInstallationId(mysqli $conexion)
{
    $result = mysqli_query($conexion, 'SELECT id, servidor_id FROM licencia ORDER BY id LIMIT 1');
    if (!$result) {
        throw new RuntimeException('No se pudo consultar la instalación de licencia local.');
    }

    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        $installationId = posGenerateInstallationId();
        $stmt = mysqli_prepare($conexion, "INSERT INTO licencia (id, codigo_licencia, fecha_vencimiento, estado, servidor_id, ultimo_uso) VALUES (1, '', '1970-01-01', 'Bloqueado', ?, NULL)");
        if (!$stmt) {
            throw new RuntimeException('No se pudo crear el registro de licencia local.');
        }
        mysqli_stmt_bind_param($stmt, 's', $installationId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (!$ok) {
            throw new RuntimeException('No se pudo guardar el identificador de instalación.');
        }
        return array('id' => 1, 'installation_id' => $installationId);
    }

    $installationId = strtolower(trim((string) $row['servidor_id']));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $installationId)) {
        // Reemplaza identificadores antiguos como "localhost" por uno aleatorio y persistente.
        $installationId = posGenerateInstallationId();
        $stmt = mysqli_prepare($conexion, 'UPDATE licencia SET servidor_id = ? WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('No se pudo actualizar el identificador de instalación.');
        }
        mysqli_stmt_bind_param($stmt, 'si', $installationId, $row['id']);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (!$ok) {
            throw new RuntimeException('No se pudo guardar el identificador de instalación.');
        }
    }

    return array('id' => (int) $row['id'], 'installation_id' => $installationId);
}

function posGenerateInstallationId()
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

function posSyncLocalLicense(mysqli $conexion, array $installation, array $license)
{
    $status = isset($license['status']) ? $license['status'] : 'expired';
    $active = ($status === 'active');
    $expires = isset($license['expires_at']) ? substr((string) $license['expires_at'], 0, 10) : '1970-01-01';
    $state = $active ? 'Activo' : ($status === 'suspended' ? 'Bloqueado' : 'Expirado');
    $today = gmdate('Y-m-d');

    $stmt = mysqli_prepare($conexion, 'UPDATE licencia SET fecha_vencimiento = ?, estado = ?, ultimo_uso = ? WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('No se pudo actualizar el estado de licencia local.');
    }
    mysqli_stmt_bind_param($stmt, 'sssi', $expires, $state, $today, $installation['id']);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        throw new RuntimeException('No se pudo actualizar el estado de licencia local.');
    }
}
?>
