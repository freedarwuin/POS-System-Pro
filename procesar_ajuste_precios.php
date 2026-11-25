<?php
session_start();
include 'conexion.php';

// Validar que sea una petición válida
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['usuario'])) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

// Recibir el JSON desde el JavaScript
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    exit;
}

if ($data['accion'] == 'individual') {
    // Actualizar un solo producto
    $id = intval($data['id']);
    $precio = floatval($data['precio']);
    
    $query = "UPDATE productos SET precio = $precio WHERE id = $id";
    $exito = mysqli_query($conexion, $query);

} elseif ($data['accion'] == 'masivo') {
    // Actualizar múltiples productos
    $ids_array = array_map('intval', $data['ids']);
    $ids_string = implode(',', $ids_array);
    $valor = floatval($data['valor']);
    $tipo = mysqli_real_escape_string($conexion, $data['tipo']);
    
    if ($tipo == 'porcentaje') {
        // Aumenta el precio un porcentaje (Ej: Si valor es 10, multiplica por 1.10)
        $query = "UPDATE productos SET precio = precio * (1 + ($valor / 100)) WHERE id IN ($ids_string)";
    } elseif ($tipo == 'fijo') {
        // Suma un monto fijo al precio actual
        $query = "UPDATE productos SET precio = precio + $valor WHERE id IN ($ids_string)";
    } elseif ($tipo == 'nuevo') {
        // Asigna exactamente el nuevo precio dictado
        $query = "UPDATE productos SET precio = $valor WHERE id IN ($ids_string)";
    } else {
        echo json_encode(['ok' => false, 'error' => 'Tipo de ajuste inválido']);
        exit;
    }
    
    $exito = mysqli_query($conexion, $query);
} else {
    $exito = false;
}

// Responder al JavaScript
echo json_encode(['ok' => $exito]);
?>