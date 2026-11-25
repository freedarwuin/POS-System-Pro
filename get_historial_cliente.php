
<?php
include 'conexion.php';

if (!isset($_GET['id'])) exit("ID no proporcionado");
$id_cliente = intval($_GET['id']);

// 1. Obtener la tasa de la configuración actual (como respaldo de seguridad)
$res_c = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1");
$conf = mysqli_fetch_assoc($res_c);
$tasa_actual = $conf['tasa_dolar'] ?? 1;

// 2. CONSULTA: Sumamos los ítems en tiempo real para evitar el error de v.total 
$query = "SELECT 
            v.id, 
            v.fecha, 
            v.pagada,
            IFNULL(v.tasa_momento, $tasa_actual) as tasa_momento,
            COALESCE(
                (SELECT SUM(cantidad * precio_usd) FROM facturas_estaticas_items WHERE id_venta = v.id),
                (SELECT SUM(dv.cantidad * dv.precio) FROM detalle_ventas dv WHERE dv.id_venta = v.id),
                v.total
            ) as total,
            (SELECT IFNULL(SUM(monto), 0) FROM pagos_credito WHERE id_venta = v.id) as total_abonado
          FROM ventas v
          WHERE v.id_cliente = $id_cliente
          ORDER BY v.id DESC";

$resultado = mysqli_query($conexion, $query);

$global_total = 0;
$global_abonado = 0;
$facturas = [];

while ($v = mysqli_fetch_assoc($resultado)) {
    $total_factura = floatval($v['total']);
    $abonos_reales = floatval($v['total_abonado']);
    $diferencia = $total_factura - $abonos_reales;

    // Lógica de tolerancia para el estatus visual
    if ($v['pagada'] == 1 || $diferencia <= 0.05) {
        $monto_abonado = $total_factura; // Forzamos el abono al total para limpieza visual
        $v['pagada'] = 1; // Aseguramos que el flag sea 1 para el JSON
    } else {
        $monto_abonado = $abonos_reales;
    }
    
    $v['monto_abonado_calculado'] = $monto_abonado;

    $facturas[] = $v;
    
    $global_total += $total_factura;
    $global_abonado += $monto_abonado;
}

// --- RESPUESTA PARA EL SELECTOR DE ABONOS ---
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode($facturas);
    exit;
}

// --- LÓGICA VISUAL (HTML) ---
$global_pendiente = $global_total - $global_abonado;
$esta_solvente = ($global_pendiente <= 0.05); 
$color_status = $esta_solvente ? '#10b981' : '#ef4444';
$fondo_status = $esta_solvente ? '#ecfdf5' : '#fef2f2';
?>

<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px;">
    <div style="background: #f8fafc; padding: 15px; border-radius: 10px; border-left: 5px solid #64748b;">
        <span style="font-size: 11px; color: #64748b; font-weight: bold; text-transform: uppercase;">Consumo Total</span>
        <div style="font-size: 18px; font-weight: 800; color: #1e293b;">$ <?php echo number_format($global_total, 2); ?></div>
    </div>
    
    <div style="background: #ecfdf5; padding: 15px; border-radius: 10px; border-left: 5px solid #10b981;">
        <span style="font-size: 11px; color: #059669; font-weight: bold; text-transform: uppercase;">Total Abonado</span>
        <div style="font-size: 18px; font-weight: 800; color: #065f46;">$ <?php echo number_format($global_abonado, 2); ?></div>
    </div>

    <div style="background: <?php echo $fondo_status; ?>; padding: 15px; border-radius: 10px; border-left: 5px solid <?php echo $color_status; ?>;">
        <span style="font-size: 11px; color: <?php echo $color_status; ?>; font-weight: bold; text-transform: uppercase;">
            <?php echo $esta_solvente ? 'Estado: Solvente' : 'Deuda Pendiente'; ?>
        </span>
        <div style="font-size: 18px; font-weight: 800; color: <?php echo $color_status; ?>;">$ <?php echo number_format(max(0, $global_pendiente), 2); ?></div>
    </div>
</div>

<div style="overflow-x:auto; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
    <table style="width:100%; border-collapse: collapse; font-family: sans-serif;">
        <thead style="background-color: #f1f5f9;">
            <tr>
                <th style="padding:12px; text-align:left; font-size:12px;"># Factura</th>
                <th style="padding:12px; text-align:center; font-size:12px;">Estado</th>
                <th style="padding:12px; text-align:right; font-size:12px;">Monto</th>
                <th style="padding:12px; text-align:right; font-size:12px; color:#059669;">Abonado</th>
                <th style="padding:12px; text-align:right; font-size:12px; color:#dc2626;">Restante</th>
                <th style="padding:12px; text-align:center; font-size:12px;">Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($facturas as $f): 
                $restante = $f['total'] - $f['monto_abonado_calculado'];
                $es_pagada = ($f['pagada'] == 1 || $restante <= 0.05);
            ?>
            <tr style="border-bottom: 1px solid #f1f5f9; background: <?php echo $es_pagada ? '#f8fafc' : 'white'; ?>">
                <td style="padding:12px; font-weight:bold;">
                    #<?php echo $f['id']; ?>
                </td>
                <td style="padding:12px; text-align:center;">
                    <?php if($es_pagada): ?>
                        <span style="background:#dcfce7; color:#166534; padding:3px 8px; border-radius:12px; font-size:10px; font-weight:bold;">PAGADA</span>
                    <?php else: ?>
                        <span style="background:#fef3c7; color:#92400e; padding:3px 8px; border-radius:12px; font-size:10px; font-weight:bold;">PENDIENTE</span>
                    <?php endif; ?>
                </td>
                <td style="padding:12px; text-align:right;">
                    $<?php echo number_format($f['total'], 2); ?>
                </td>
                <td style="padding:12px; text-align:right; color:#059669; font-weight:bold;">
                    $<?php echo number_format($f['monto_abonado_calculado'], 2); ?>
                </td>
                <td style="padding:12px; text-align:right; color:#dc2626; font-weight:bold;">
                    $<?php echo number_format(max(0, $restante), 2); ?>
                </td>
                <td style="padding:12px; text-align:center;">
                    <button onclick="verDetalleFactura(<?php echo $f['id']; ?>)" 
                            style="background:#4f46e5; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:11px;">
                        Ver Detalle
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
