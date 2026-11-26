<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function responderJson($status, array $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if (empty($_SESSION['usuario']) || (($_SESSION['rol'] ?? '') !== 'Admin')) {
    responderJson(403, ['ok' => false, 'mensaje' => 'Acceso denegado. Se requiere una sesión de administrador.']);
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    responderJson(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

if ($metodo === 'POST') {
    $tokenSesion = (string) ($_SESSION['csrf_tasa_bcv'] ?? '');
    $tokenSolicitud = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($tokenSesion === '' || $tokenSolicitud === '' || !hash_equals($tokenSesion, $tokenSolicitud)) {
        responderJson(403, ['ok' => false, 'mensaje' => 'La sesión expiró. Recarga la página e inténtalo nuevamente.']);
    }
}

function obtenerHtmlBcv()
{
    $url = 'https://www.bcv.org.ve/';
    $html = false;

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'POS-System-Pro/1.0 (consulta de tasa BCV)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $html = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($html === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('No fue posible consultar el sitio BCV. ' . ($error !== '' ? $error : 'HTTP ' . $status));
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => "User-Agent: POS-System-Pro/1.0 (consulta de tasa BCV)\r\nAccept: text/html\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $html = @file_get_contents($url, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) {
                $status = (int) $match[1];
            }
        }
        if ($html === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('No fue posible consultar el sitio BCV.' . ($status ? ' HTTP ' . $status : ''));
        }
    }

    if (!is_string($html) || $html === '') {
        throw new RuntimeException('El BCV devolvió una respuesta vacía.');
    }
    return $html;
}

function normalizarTasa($texto)
{
    $texto = html_entity_decode(strip_tags((string) $texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = trim(preg_replace('/\s+/u', ' ', $texto));
    if (!preg_match('/(?<!\d)(\d{1,3}(?:\.\d{3})*(?:,\d+)?|\d+(?:,\d+)?)(?!\d)/', $texto, $match)) {
        return null;
    }
    $numero = str_replace('.', '', $match[1]);
    $numero = str_replace(',', '.', $numero);
    $tasa = (float) $numero;
    return is_finite($tasa) && $tasa > 0 ? $tasa : null;
}

function extraerTasaBcv($html)
{
    $candidatos = [];
    if (class_exists('DOMDocument')) {
        $documento = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $cargado = $documento->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        if ($cargado) {
            $xpath = new DOMXPath($documento);
            $nodos = $xpath->query('//*[@id="dolar"]');
            if ($nodos && $nodos->length > 0) {
                $textos = $xpath->query('.//strong | .//span | .//div', $nodos->item(0));
                if ($textos) {
                    foreach ($textos as $nodo) {
                        $valor = normalizarTasa($nodo->textContent);
                        if ($valor !== null) {
                            $candidatos[] = $valor;
                        }
                    }
                }
                if (!$candidatos) {
                    $valor = normalizarTasa($nodos->item(0)->textContent);
                    if ($valor !== null) {
                        $candidatos[] = $valor;
                    }
                }
            }
        }
    }

    // Fallback para instalaciones PHP sin la extensión DOM.
    if (!$candidatos && preg_match('/id=["\']dolar["\'][^>]*>(.{0,10000}?)(?:id=["\']euro["\']|$)/is', $html, $bloque)) {
        preg_match_all('/\d{1,3}(?:\.\d{3})*(?:,\d+)?|\d+(?:,\d+)?/', strip_tags($bloque[1]), $numeros);
        foreach ($numeros[0] as $numero) {
            $valor = normalizarTasa($numero);
            if ($valor !== null) {
                $candidatos[] = $valor;
            }
        }
    }

    if (!$candidatos) {
        throw new RuntimeException('No se encontró la tasa USD en la página del BCV.');
    }
    $tasa = (float) end($candidatos);
    if (!is_finite($tasa) || $tasa <= 0 || $tasa > 1000000000) {
        throw new RuntimeException('El valor publicado por el BCV no parece una tasa válida.');
    }
    return $tasa;
}

try {
    $tasa = extraerTasaBcv(obtenerHtmlBcv());
    if ($metodo === 'GET') {
        responderJson(200, [
            'ok' => true,
            'moneda' => 'USD',
            'tasa' => $tasa,
            'fuente' => 'Banco Central de Venezuela',
            'url' => 'https://www.bcv.org.ve/',
        ]);
    }

    require __DIR__ . '/conexion.php';
    $usuario = (string) $_SESSION['usuario'];
    mysqli_begin_transaction($conexion);

    $stmt = mysqli_prepare($conexion, 'UPDATE configuracion SET tasa_dolar = ? WHERE id = 1');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la actualización de la tasa.');
    }
    mysqli_stmt_bind_param($stmt, 'd', $tasa);
    if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) < 0) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('No se pudo guardar la tasa: ' . $error);
    }
    mysqli_stmt_close($stmt);

    $detalles = sprintf('Tasa USD actualizada desde el BCV: %.8F Bs/USD.', $tasa);
    $auditoria = mysqli_prepare($conexion, 'INSERT INTO auditoria (usuario, accion, detalles) VALUES (?, ?, ?)');
    if (!$auditoria) {
        throw new RuntimeException('No se pudo preparar el registro de auditoría.');
    }
    $accion = 'ACTUALIZACIÓN TASA BCV';
    mysqli_stmt_bind_param($auditoria, 'sss', $usuario, $accion, $detalles);
    if (!mysqli_stmt_execute($auditoria)) {
        $error = mysqli_stmt_error($auditoria);
        mysqli_stmt_close($auditoria);
        throw new RuntimeException('No se pudo registrar la auditoría: ' . $error);
    }
    mysqli_stmt_close($auditoria);
    mysqli_commit($conexion);

    responderJson(200, [
        'ok' => true,
        'moneda' => 'USD',
        'tasa' => $tasa,
        'mensaje' => 'Tasa BCV actualizada correctamente.',
        'fuente' => 'Banco Central de Venezuela',
        'url' => 'https://www.bcv.org.ve/',
    ]);
} catch (Throwable $e) {
    if (isset($conexion) && $conexion instanceof mysqli) {
        @mysqli_rollback($conexion);
    }
    error_log('[api_tasa_bcv] ' . $e->getMessage());
    responderJson(502, ['ok' => false, 'mensaje' => 'No se pudo obtener o guardar la tasa del BCV. Verifica la conexión del servidor y vuelve a intentar.']);
}
