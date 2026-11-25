<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    exit('Acceso denegado');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_proveedor = (int)$_POST['id_proveedor'];
    $nro_factura = mysqli_real_escape_string($conexion, trim($_POST['nro_factura']));
    $usuario_act = mysqli_real_escape_string($conexion, $_SESSION['usuario']);
    
    // Arrays que vienen del formulario dinámico
    $ids_productos = $_POST['id_producto'];
    $cantidades = $_POST['cantidad'];
    $costos = $_POST['costo_unitario'];

    // 1. Crear la cabecera de la compra
    $sql_compra = "INSERT INTO compras_proveedores (id_proveedor, nro_factura, usuario_registro) 
                   VALUES ($id_proveedor, '$nro_factura', '$usuario_act')";
    
    if (mysqli_query($conexion, $sql_compra)) {
        $id_compra = mysqli_insert_id($conexion);
        $total_general_factura = 0;

        // 2. Procesar cada artículo recibido
        for ($i = 0; $i < count($ids_productos); $i++) {
            $id_prod = (int)$ids_productos[$i];
            $cant = (float)$cantidades[$i];
            $costo_u = (float)$costos[$i];
            
            if ($id_prod > 0 && $cant > 0) {
                $total_linea = $cant * $costo_u;
                $total_general_factura += $total_linea;

                // A. Guardar en el detalle de la compra
                $sql_detalle = "INSERT INTO detalle_compras (id_compra, id_producto, cantidad, costo_unitario, total_linea) 
                                VALUES ($id_compra, $id_prod, $cant, $costo_u, $total_linea)";
                mysqli_query($conexion, $sql_detalle);

                // B. MAGIA: Actualizar Stock y Costo de Referencia en el Inventario
                // NOTA: No tocamos la columna 'precio' o 'precio_usd' para que tu precio de venta siga siendo manual
                $sql_stock = "UPDATE productos 
                              SET stock = stock + $cant, 
                                  ultimo_costo = $costo_u 
                              WHERE id = $id_prod";
                mysqli_query($conexion, $sql_stock);

                // C. Obtener nombre del producto para el log
                $res_n = mysqli_query($conexion, "SELECT nombre FROM productos WHERE id = $id_prod");
                $prod_data = mysqli_fetch_assoc($res_n);
                $nombre_p = mysqli_real_escape_string($conexion, $prod_data['nombre']);

                // D. Registrar en el Historial de Inventario para auditoría
                $detalles_log = "Ingreso por Compra Factura #$nro_factura (Costo U: $costo_u $). Producto: $nombre_p";
                $sql_log = "INSERT INTO historial_inventario (id_producto, usuario, accion, cantidad, detalle, fecha) 
                            VALUES ($id_prod, '$usuario_act', 'INGRESO_COMPRA', $cant, '$detalles_log', NOW())";
                mysqli_query($conexion, $sql_log);
            }
        }

        // 3. Actualizar el total general de la factura de compra
        mysqli_query($conexion, "UPDATE compras_proveedores SET total_compra = $total_general_factura WHERE id = $id_compra");

        // Redireccionar con éxito
        echo "<script>
                alert('Factura registrada y stock actualizado correctamente.');
                window.location.href = 'nueva_compra.php';
              </script>";
    } else {
        echo "Error al registrar la cabecera de la compra: " . mysqli_error($conexion);
    }
}
?>