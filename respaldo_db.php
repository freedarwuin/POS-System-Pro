<?php
session_start();

// Seguridad: Solo el Administrador puede generar respaldos
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Admin') {
    header("Location: ventas.php");
    exit();
}

// Incluimos tu archivo de conexión existente
include 'conexion.php';

// Cambiar la zona horaria si es necesario para el nombre del archivo
date_default_timezone_get();
$fecha = date("Y-m-d_H-i-s");
$nombre_archivo = "respaldo_db_" . $fecha . ".sql";

// Configurar cabeceras HTTP para forzar la descarga del archivo .sql
header("Content-Type: application/octet-stream");
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"" . $nombre_archivo . "\"");

// Obtener el listado de todas las tablas de la base de datos
$tablas = array();
$result = mysqli_query($conexion, "SHOW TABLES");
while ($row = mysqli_fetch_row($result)) {
    $tablas[] = $row[0];
}

// Generar el contenido del archivo SQL
$sql_backup = "-- RECONSTRUCCIÓN DE BASE DE DATOS - POS SYSTEM PRO\n";
$sql_backup .= "-- Fecha de generación: " . date('Y-m-d H:i:s') . "\n\n";
$sql_backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tablas as $tabla) {
    // 1. Estructura de la tabla (CREATE TABLE)
    $result_create = mysqli_query($conexion, "SHOW CREATE TABLE `$tabla`");
    $row_create = mysqli_fetch_row($result_create);
    
    $sql_backup .= "DROP TABLE IF EXISTS `$tabla`;\n";
    $sql_backup .= $row_create[1] . ";\n\n";
    
    // 2. Datos de la tabla (INSERT INTO)
    $result_data = mysqli_query($conexion, "SELECT * FROM `$tabla`");
    $num_fields = mysqli_num_fields($result_data);
    
    while ($row_data = mysqli_fetch_row($result_data)) {
        $sql_backup .= "INSERT INTO `$tabla` VALUES(";
        for ($i = 0; $i < $num_fields; $i++) {
            if (isset($row_data[$i])) {
                // Escapar caracteres especiales para evitar errores de sintaxis SQL
                $value = mysqli_real_escape_string($conexion, $row_data[$i]);
                $sql_backup .= '"' . $value . '"';
            } else {
                $sql_backup .= 'NULL';
            }
            if ($i < ($num_fields - 1)) {
                $sql_backup .= ',';
            }
        }
        $sql_backup .= ");\n";
    }
    $sql_backup .= "\n\n";
}

$sql_backup .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Imprimir el resultado directamente (esto escribe el contenido dentro del archivo descargado)
echo $sql_backup;
exit();
?>