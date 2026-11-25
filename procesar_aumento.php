<?php
session_start();
include 'conexion.php';

// Verificación de seguridad básica
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Admin') {
    echo "error_sesion";
    exit();
}

$usuario_activo = $_SESSION['usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo']; 
    $valor = (float)$_POST['valor'];
    $depto_id = isset($_POST['id_depto']) ? (int)$_POST['id_depto'] : 0;
    
    // Determinar a quiénes afecta el cambio
    if ($depto_id > 0) {
        $where_clause = "id_departamento = $depto_id";
        $detalle_h = "AJUSTE POR DEPTO ID: $depto_id";
    } else if (isset($_POST['ids'])) {
        $ids = json_decode($_POST['ids']);
        $ids_str = implode(',', array_map('intval', $ids));
        $where_clause = "id IN ($ids_str)";
        $detalle_h = "AJUSTE MANUAL (" . count($ids) . " productos)";
    } else {
        echo "no_targets";
        exit();
    }

    if ($valor != 0) {
        // Construir SQL
        $sql = ($tipo === 'porcentaje') 
            ? "UPDATE productos SET precio = precio + (precio * ($valor / 100)) WHERE $where_clause"
            : "UPDATE productos SET precio = precio + $valor WHERE $where_clause";
        
        if (mysqli_query($conexion, $sql)) {
            // Registro en historial
            $msg_historial = "$detalle_h - $tipo de $valor";
            mysqli_query($conexion, "INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, detalle, usuario) 
                         VALUES (0, 'ACTUALIZACIÓN', 0, '$msg_historial', '$usuario_activo')");
            echo "success";
        } else {
            echo "error_db";
        }
    } else {
        echo "valor_cero";
    }
}