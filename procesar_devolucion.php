<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    exit('Acceso denegado');
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ACCIÓN 1: LISTAR ARTÍCULOS EN EL MODAL VIA AJAX
if ($action == 'listar') {
    $id_venta = isset($_GET['id_venta']) ? (int)$_GET['id_venta'] : 0;
    $items = [];
    
    $sql = "SELECT id, id_producto, descripcion, cantidad, precio_usd, precio_bs FROM facturas_estaticas_items WHERE id_venta = $id_venta AND cantidad > 0";
    $res = mysqli_query($conexion, $sql);
    
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $items[] = $row;
        }
    }
    header('Content-Type: application/json');
    echo json_encode($items);
    exit;
}

// ACCIÓN 2: PROCESAR DEVOLUCIÓN DE UNA CANTIDAD ESPECÍFICA
if ($action == 'devolver') {
    if (!isset($_POST['id_detalle']) || !isset($_POST['cantidad_devolver'])) {
        exit("Datos insuficientes.");
    }

    $id_detalle = (int)$_POST['id_detalle'];
    $cant_devolver = floatval($_POST['cantidad_devolver']);

    // 1. Obtener datos del renglón estático
    $sql_item = "SELECT * FROM facturas_estaticas_items WHERE id = $id_detalle";
    $res_item = mysqli_query($conexion, $sql_item);
    $item = mysqli_fetch_assoc($res_item);

    if (!$item) {
        exit("El artículo seleccionado no existe en el sistema.");
    }

    $id_venta = (int)$item['id_venta'];
    $id_producto = (int)$item['id_producto'];
    $cant_original = floatval($item['cantidad']);

    if ($cant_devolver > $cant_original) {
        exit("Error: No puedes devolver más de la cantidad existente ({$cant_original}).");
    }

    // Obtener los datos maestros de la venta para saber cómo se pagó
    $res_master = mysqli_query($conexion, "SELECT * FROM ventas WHERE id = $id_venta");
    $master = mysqli_fetch_assoc($res_master);
    $tasa = floatval($master['tasa_momento']);
    $metodo_pago = trim($master['metodo_pago']);

    // 2. Regresar la cantidad correspondiente al stock en el Inventario
    if ($id_producto > 0) {
        mysqli_query($conexion, "UPDATE productos SET stock = stock + $cant_devolver WHERE id = $id_producto");
        
        // Registrar en el historial de inventario la devolución
        $usuario_act = mysqli_real_escape_string($conexion, $_SESSION['usuario']);
        $detalles_log = mysqli_real_escape_string($conexion, "Devolución en Factura #{$id_venta}: Reingreso de {$cant_devolver} unds de " . $item['descripcion']);
        @mysqli_query($conexion, "INSERT INTO historial_inventario (id_producto, usuario, accion, cantidad, detalle, fecha) VALUES ($id_producto, '$usuario_act', 'DEVOLUCION', $cant_devolver, '$detalles_log', NOW())");
    }

    // 3. Recalcular costos del ítem afectado
    $precio_u_usd = floatval($item['precio_usd']);
    $precio_u_bs = floatval($item['precio_bs']);
    
    $restar_usd = $precio_u_usd * $cant_devolver;
    $restar_bs = $precio_u_bs * $cant_devolver;

    // Modificar la tabla estática (NUNCA BORRAR para mantener el historial visual de la factura)
    mysqli_query($conexion, "UPDATE facturas_estaticas_items SET cantidad = cantidad - $cant_devolver, total_linea_bs = total_linea_bs - $restar_bs WHERE id = $id_detalle");

    // Sincronizar con la tabla original 'detalle_ventas' para remover el ítem por completo
    mysqli_query($conexion, "UPDATE detalle_ventas SET cantidad = cantidad - $cant_devolver WHERE id_venta = $id_venta AND id_producto = $id_producto");
    mysqli_query($conexion, "UPDATE detalle_ventas SET cantidad = cantidad - $cant_devolver WHERE id_ventas = $id_venta AND id_producto = $id_producto");
    mysqli_query($conexion, "DELETE FROM detalle_ventas WHERE (id_venta = $id_venta OR id_ventas = $id_venta) AND cantidad <= 0");

    // 4. DEBITAR PROPORCIONALMENTE DE LOS CAMPOS DE CORTE EN LA MAESTRA
    if ($metodo_pago == 'Dolares $' || $metodo_pago == 'Efectivo $') {
        mysqli_query($conexion, "UPDATE ventas SET pago_efectivo_usd = pago_efectivo_usd - $restar_usd WHERE id = $id_venta");
    } elseif ($metodo_pago == 'Efectivo Bs.') {
        mysqli_query($conexion, "UPDATE ventas SET pago_efectivo_bs = pago_efectivo_bs - $restar_bs WHERE id = $id_venta");
    } elseif ($metodo_pago == 'Punto' || $metodo_pago == 'Punto de Venta') {
        mysqli_query($conexion, "UPDATE ventas SET pago_punto_bs = pago_punto_bs - $restar_bs WHERE id = $id_venta");
    } elseif ($metodo_pago == 'Pago Móvil') {
        mysqli_query($conexion, "UPDATE ventas SET pago_movil_bs = pago_movil_bs - $restar_bs WHERE id = $id_venta");
    } elseif ($metodo_pago == 'Crédito') {
        $cliente_clean = mysqli_real_escape_string($conexion, $master['cliente']);
        mysqli_query($conexion, "UPDATE clientes SET deuda_actual = deuda_actual - $restar_usd WHERE nombre = '$cliente_clean'");
    } else {
        // Para pagos Múltiples, descontamos priorizando los campos con saldo disponible
        $p_efec_usd = floatval($master['pago_efectivo_usd']);
        $p_punto    = floatval($master['pago_punto_bs']);
        $p_movil    = floatval($master['pago_movil_bs']);

        if ($p_efec_usd >= $restar_usd) {
            mysqli_query($conexion, "UPDATE ventas SET pago_efectivo_usd = pago_efectivo_usd - $restar_usd WHERE id = $id_venta");
        } elseif ($p_punto >= $restar_bs) {
            mysqli_query($conexion, "UPDATE ventas SET pago_punto_bs = pago_punto_bs - $restar_bs WHERE id = $id_venta");
        } elseif ($p_movil >= $restar_bs) {
            mysqli_query($conexion, "UPDATE ventas SET pago_movil_bs = pago_movil_bs - $restar_bs WHERE id = $id_venta");
        } else {
            mysqli_query($conexion, "UPDATE ventas SET pago_efectivo_bs = pago_efectivo_bs - $restar_bs WHERE id = $id_venta");
        }
    }

    // 5. Actualizar el total general de la venta
    mysqli_query($conexion, "UPDATE ventas SET total = total - $restar_usd WHERE id = $id_venta");

    // Si la venta llegó a 0 USD en total, pasa a estado DEVUELTA para que el corte la ignore por completo
    $res_check = mysqli_query($conexion, "SELECT total FROM ventas WHERE id = $id_venta");
    $check_vta = mysqli_fetch_assoc($res_check);
    if (floatval($check_vta['total']) <= 0) {
        mysqli_query($conexion, "UPDATE ventas SET estado = 'DEVUELTA', total = 0, pago_efectivo_usd = 0, pago_efectivo_bs = 0, pago_punto_bs = 0, pago_movil_bs = 0 WHERE id = $id_venta");
    }

    echo "OK"; 
    exit;
}
?>