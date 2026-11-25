
<?php
include 'conexion.php';

// =========================================================================
// 1. PROCESAR ACCIÓN: MARCAR COMO PAGADA (Mantiene el precio fijo)
// =========================================================================
if (isset($_GET['cancelar_factura'])) {
    $id_venta = intval($_GET['cancelar_factura']);
    
    // Obtener el total exacto de la factura sumando los ítems en tiempo real (Igual que la visual)
    $sql_det = "SELECT SUM(cantidad * precio_usd) AS tot FROM facturas_estaticas_items WHERE id_venta = $id_venta";
    $res_det = mysqli_query($conexion, $sql_det);
    $row_det = mysqli_fetch_assoc($res_det);
    $total_calc = floatval($row_det['tot'] ?? 0);
    
    if ($total_calc == 0) {
        $sql_ant = "SELECT SUM(cantidad * precio) AS tot FROM detalle_ventas WHERE id_venta = $id_venta";
        $res_ant = mysqli_query($conexion, $sql_ant);
        if (!$res_ant) {
            $sql_ant = "SELECT SUM(cantidad * precio) AS tot FROM detalle_ventas WHERE id_ventas = $id_venta";
            $res_ant = mysqli_query($conexion, $sql_ant);
        }
        $row_ant = mysqli_fetch_assoc($res_ant);
        $total_calc = floatval($row_ant['tot'] ?? 0);
    }
    
    if ($total_calc == 0) {
        $sql_v = "SELECT total FROM ventas WHERE id = $id_venta";
        $res_v = mysqli_query($conexion, $sql_v);
        $row_v = mysqli_fetch_assoc($res_v);
        $total_calc = floatval($row_v['total'] ?? 0);
    }
    
    // Obtener la sumatoria de abonos que ya se le han hecho a esta factura específica
    $sql_abonos = "SELECT IFNULL(SUM(monto), 0) as abonado FROM pagos_credito WHERE id_venta = $id_venta";
    $res_abonos = mysqli_query($conexion, $sql_abonos);
    $row_abonos = mysqli_fetch_assoc($res_abonos);
    $ya_abonado = floatval($row_abonos['abonado']);
    
    // Calcular el faltante exacto en dólares para saldarla por completo
    $restante_usd = max(0, $total_calc - $ya_abonado);
    
    // Si queda un saldo pendiente, lo registramos como el abono final para liquidarla
    if ($restante_usd > 0) {
        mysqli_query($conexion, "INSERT INTO pagos_credito (id_venta, monto, fecha) VALUES ($id_venta, $restante_usd, NOW())");
    }
    
    // Cambiar el flag de la venta a pagada
    mysqli_query($conexion, "UPDATE ventas SET pagada = 1 WHERE id = $id_venta");
    
    // ¡SOLUCIÓN AQUÍ! Redireccionamos directamente a la vista de la factura para ver el sello de CANCELADO
    header("Location: creditos.php?id=" . $id_venta);
    exit;
}

// =========================================================================
// 2. LOGICA DE CARGA Y VISUALIZACIÓN DE LA FACTURA
// =========================================================================
if (!isset($_GET['id'])) exit("No hay ID");
$id = intval($_GET['id']);

// Obtener los datos generales de la venta
$v_query = "SELECT v.*, c.nombre as cliente_nombre 
            FROM ventas v 
            LEFT JOIN clientes_credito c ON v.id_cliente = c.id 
            WHERE v.id = $id";
$v_res = mysqli_query($conexion, $v_query);
$v = mysqli_fetch_assoc($v_res);

if (!$v) exit("Factura no encontrada");

// Tasa congelada en el momento de la venta
$tasa_historica_venta = isset($v['tasa_momento']) ? floatval($v['tasa_momento']) : 1;

// Consultar la tabla estática primero
$sql_detalles = "SELECT descripcion AS nombre_p, cantidad, precio_usd, (cantidad * precio_usd) AS total_linea_usd, precio_bs, total_linea_bs 
                 FROM facturas_estaticas_items 
                 WHERE id_venta = $id";
                 
$res_detalles = mysqli_query($conexion, $sql_detalles);
$num_items = mysqli_num_rows($res_detalles);

$es_factura_antigua = false;
$items_array = [];
$gran_total_bs = 0;
$gran_total_usd = 0;

if ($num_items == 0) {
    $es_factura_antigua = true;
    
    $sql_antiguo = "SELECT p.nombre AS nombre_p, dv.cantidad, dv.precio AS precio_usd, 
                           (dv.cantidad * dv.precio) AS total_linea_usd 
                    FROM detalle_ventas dv 
                    LEFT JOIN productos p ON dv.id_producto = p.id 
                    WHERE dv.id_venta = $id";
                    
    $res_detalles_antiguo = mysqli_query($conexion, $sql_antiguo);
    
    if (!$res_detalles_antiguo) {
         $sql_antiguo_alt = "SELECT p.nombre AS nombre_p, dv.cantidad, dv.precio AS precio_usd, 
                                    (dv.cantidad * dv.precio) AS total_linea_usd 
                             FROM detalle_ventas dv 
                             LEFT JOIN productos p ON dv.id_producto = p.id 
                             WHERE dv.id_ventas = $id";
         $res_detalles_antiguo = mysqli_query($conexion, $sql_antiguo_alt);
    }
    
    if ($res_detalles_antiguo) {
        while ($row = mysqli_fetch_assoc($res_detalles_antiguo)) {
            $row['total_linea_bs'] = $row['total_linea_usd'] * $tasa_historica_venta;
            $items_array[] = $row;
            $gran_total_bs += $row['total_linea_bs'];
            $gran_total_usd += $row['total_linea_usd'];
        }
    }
} else {
    while ($row = mysqli_fetch_assoc($res_detalles)) {
        if (!isset($row['total_linea_bs']) || $row['total_linea_bs'] == 0) {
            $row['total_linea_bs'] = $row['total_linea_usd'] * $tasa_historica_venta;
        }
        $items_array[] = $row;
        $gran_total_bs += $row['total_linea_bs'];
        $gran_total_usd += $row['total_linea_usd'];
    }
}

if ($gran_total_usd == 0) {
    $gran_total_usd = floatval($v['total']);
}
if ($gran_total_bs == 0) {
    $gran_total_bs = $gran_total_usd * $tasa_historica_venta;
}
?>

<div style="padding: 25px; font-family: 'Arial', sans-serif; position: relative; background: white; min-height: 450px;">

    <?php if(isset($v['pagada']) && $v['pagada'] == 1): ?>
    <div style="
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-25deg);
        border: 10px double #dc2626;
        color: #dc2626;
        font-size: 5rem;
        font-weight: 900;
        padding: 15px 40px;
        opacity: 0.15;
        z-index: 1000;
        pointer-events: none;
        text-transform: uppercase;
        border-radius: 15px;
        letter-spacing: 12px;
        white-space: nowrap;
        font-family: 'Impact', sans-serif;
    ">
        CANCELADO
    </div>
    
    <div style="text-align: center; background: #fee2e2; color: #991b1b; padding: 10px; margin-bottom: 20px; border-radius: 8px; font-weight: 800; border: 2px solid #fca5a5; text-transform: uppercase; letter-spacing: 1px;">
        Esta factura ha sido pagada en su totalidad
    </div>
    <?php endif; ?>

    <div style="border-bottom: 3px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">
        <h2 style="margin:0; color:#1e293b; font-size: 1.6rem;">FACTURA #<?php echo $v['id']; ?></h2>
        <div style="display: flex; justify-content: space-between; margin-top: 10px;">
            <span style="color:#64748b;"><b>Cliente:</b> <?php echo htmlspecialchars($v['cliente_nombre'] ?? 'General'); ?></span>
            <span style="color:#64748b;"><b>Fecha:</b> <?php echo date('d/m/Y h:i A', strtotime($v['fecha'])); ?></span>
        </div>
    </div>

    <table style="width:100%; border-collapse: collapse;">
        <thead>
            <tr style="color:#94a3b8; font-size:0.75rem; text-transform:uppercase; border-bottom: 2px solid #f1f5f9;">
                <th style="text-align:left; padding:10px;">Producto</th>
                <th style="text-align:center; padding:10px;">Cant.</th>
                <th style="text-align:right; padding:10px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($items_array) > 0): ?>
                <?php foreach($items_array as $it): ?>
                <tr style="border-bottom: 1px solid #f8fafc;">
                    <td style="padding:12px 10px; font-size:0.9rem; color:#334155;"><?php echo htmlspecialchars($it['nombre_p'] ?? 'Artículo / Avance'); ?></td>
                    <td style="text-align:center; font-size:0.9rem; color:#64748b;"><?php echo number_format($it['cantidad'], 2); ?></td>
                    <td style="text-align:right; font-weight:700; font-size:0.9rem; color:#1e293b;">$<?php echo number_format($it['total_linea_usd'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="padding:20px; text-align:center; color:#64748b; font-style:italic;">No se encontraron detalles para esta factura.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top:30px; background:#f8fafc; padding:20px; border-radius:12px; border: 1px solid #e2e8f0; text-align:right;">
        <span style="color:#64748b; font-size:0.8rem; font-weight:bold;">MONTO TOTAL A PAGAR:</span>
        <strong style="font-size:2rem; color:#4f46e5; display:block; margin: 5px 0;">$ <?php echo number_format($gran_total_usd, 2); ?></strong>
        <span style="font-size:1.3rem; color:#15803d; font-weight:bold;">Bs. <?php echo number_format($gran_total_bs, 2, ',', '.'); ?></span>
    </div>

    <?php if(isset($v['pagada']) && $v['pagada'] == 0): ?>
    <div style="margin-top: 30px; text-align: center;">
        <a href="creditos.php?cancelar_factura=<?php echo $id; ?>&id_cliente=<?php echo $v['id_cliente']; ?>" 
           onclick="return confirm('¿Confirmar pago total de esta factura?')"
           style="background: #10b981; color: white; padding: 15px 35px; text-decoration: none; border-radius: 12px; font-weight: bold; display: inline-block; font-size: 1.1rem; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3); transition: background 0.2s;">
            💰 Marcar como Pagada
        </a>
    </div>
    <?php endif; ?>
</div>
