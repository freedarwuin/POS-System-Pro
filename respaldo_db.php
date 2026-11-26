<?php
/**
 * Descarga un respaldo SQL completo de las tablas de la base de datos.
 * Solo está disponible para usuarios administradores.
 */
session_start();

if (empty($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'Admin') {
    header('Location: ventas.php');
    exit();
}

require_once __DIR__ . '/conexion.php';

mysqli_set_charset($conexion, 'utf8mb4');

function respaldoDbIdentificador(string $identificador): string
{
    return '`' . str_replace('`', '``', $identificador) . '`';
}

function respaldoDbError(string $mensaje): void
{
    error_log('[respaldo_db] ' . $mensaje);
    http_response_code(500);
    exit('No se pudo generar el respaldo. Revisa el registro de errores del servidor.');
}

// Cargar primero los nombres y las estructuras para no iniciar una descarga
// si la conexión no permite consultar las tablas.
$resultadoTablas = mysqli_query($conexion, 'SHOW TABLES');
if (!$resultadoTablas) {
    respaldoDbError(mysqli_error($conexion));
}

$tablas = [];
while ($fila = mysqli_fetch_row($resultadoTablas)) {
    $tablas[] = $fila[0];
}
mysqli_free_result($resultadoTablas);

$estructuras = [];
foreach ($tablas as $tabla) {
    $tablaSql = respaldoDbIdentificador($tabla);
    $resultadoCreate = mysqli_query($conexion, 'SHOW CREATE TABLE ' . $tablaSql);
    if (!$resultadoCreate) {
        respaldoDbError(mysqli_error($conexion));
    }

    $filaCreate = mysqli_fetch_row($resultadoCreate);
    mysqli_free_result($resultadoCreate);
    if (!isset($filaCreate[1])) {
        respaldoDbError('No se pudo obtener la estructura de la tabla ' . $tabla);
    }
    $estructuras[$tabla] = $filaCreate[1];
}

$fecha = date('Y-m-d_H-i-s');
$nombreArchivo = 'respaldo_db_' . $fecha . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Enviar el SQL progresivamente para evitar conservar el respaldo completo
// en memoria cuando la base de datos tenga muchos registros.
echo "-- Respaldo de base de datos - POS System Pro\n";
echo '-- Generado: ' . date('Y-m-d H:i:s') . "\n\n";
echo "SET NAMES utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tablas as $tabla) {
    $tablaSql = respaldoDbIdentificador($tabla);
    echo '-- Estructura de ' . $tabla . "\n";
    echo 'DROP TABLE IF EXISTS ' . $tablaSql . ";\n";
    echo $estructuras[$tabla] . ";\n\n";

    $resultadoVacio = mysqli_query($conexion, 'SELECT * FROM ' . $tablaSql . ' LIMIT 0');
    if (!$resultadoVacio) {
        respaldoDbError(mysqli_error($conexion));
    }
    $campos = mysqli_fetch_fields($resultadoVacio);
    mysqli_free_result($resultadoVacio);

    if (!$campos) {
        continue;
    }

    $nombresCampos = [];
    foreach ($campos as $campo) {
        $nombresCampos[] = respaldoDbIdentificador($campo->name);
    }

    $resultadoDatos = mysqli_query($conexion, 'SELECT * FROM ' . $tablaSql, MYSQLI_USE_RESULT);
    if (!$resultadoDatos) {
        respaldoDbError(mysqli_error($conexion));
    }

    $columnasSql = implode(', ', $nombresCampos);
    while ($fila = mysqli_fetch_row($resultadoDatos)) {
        $valoresSql = [];
        foreach ($fila as $valor) {
            $valoresSql[] = $valor === null
                ? 'NULL'
                : "'" . mysqli_real_escape_string($conexion, $valor) . "'";
        }

        echo 'INSERT INTO ' . $tablaSql . ' (' . $columnasSql . ') VALUES ('
            . implode(', ', $valoresSql) . ");\n";
    }
    mysqli_free_result($resultadoDatos);
    echo "\n";

    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
exit();
