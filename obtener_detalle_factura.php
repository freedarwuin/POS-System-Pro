<?php
include 'conexion.php';

if (!isset($_GET['id'])) {
    exit("<p class='text-danger p-3'>ID de factura no proporcionado.</p>");
}

$id_venta = (int)$_GET['id'];

// Función para truncar a 2 decimales exactos sin redondear hacia arriba
function truncar2($numero) {
    return floor(((float)$numero) * 100) / 100;
}

// 1. Obtener datos clave de la cabecera de la venta
$sql_v = "SELECT * FROM ventas WHERE id = $id_venta";
$res_v = mysqli_query($conexion, $sql_v);
$venta = mysqli_fetch_assoc($res_v);

if (!$venta) {
    exit("<p class='text-danger p-3'>Factura #$id_venta no encontrada.</p>");
}

$tasa = isset($venta['tasa_momento']) ? (float)$venta['tasa_momento'] : 0;
if ($tasa <= 0) {
    if (isset($venta['tasa'])) $tasa = (float)$venta['tasa'];
    else $tasa = 1;
}

// 2. Comprobar si la venta es Cashea
$es_cashea = (isset($venta['metodo_pago']) && $venta['metodo_pago'] === 'Cashea');
$inicial_usd = 0;
$financiado_usd = 0;
$recargo_usd = 0;

if ($es_cashea) {
    $sql_c = "SELECT * FROM ventas_cashea WHERE id_venta = $id_venta LIMIT 1";
    $res_c = mysqli_query($conexion, $sql_c);
    if ($res_c && mysqli_num_rows($res_c) > 0) {
        $datos_cashea = mysqli_fetch_assoc($res_c);
        $inicial_usd = (float)$datos_cashea['monto_inicial'];
        $financiado_usd = (float)$datos_cashea['monto_financiado'];
        $recargo_usd = (float)($datos_cashea['monto_recargo'] ?? 0);
    }
}

// 3. Comprobar o generar el respaldo estático
$sql_check = "SELECT * FROM facturas_estaticas_items WHERE id_venta = $id_venta";
$res_check = mysqli_query($conexion, $sql_check);

if (mysqli_num_rows($res_check) == 0) {
    $sql_origen = "SELECT dv.*, p.nombre 
                   FROM detalle_ventas dv 
                   LEFT JOIN productos p ON dv.id_producto = p.id 
                   WHERE dv.id_venta = $id_venta";
                   
    $res_origen = mysqli_query($conexion, $sql_origen);
    
    if (!$res_origen || mysqli_num_rows($res_origen) == 0) {
        $sql_origen = "SELECT dv.*, p.nombre 
                       FROM detalle_ventas dv 
                       LEFT JOIN productos p ON dv.id_producto = p.id 
                       WHERE dv.id_ventas = $id_venta";
        $res_origen = mysqli_query($conexion, $sql_origen);
    }

    if ($res_origen && mysqli_num_rows($res_origen) > 0) {
        while ($item = mysqli_fetch_assoc($res_origen)) {
            $id_prod = isset($item['id_producto']) ? (int)$item['id_producto'] : 0;
            
            if (!empty($item['nombre'])) {
                $nombre_prod = $item['nombre'];
            } elseif (isset($item['descripcion']) && !empty($item['descripcion'])) {
                $nombre_prod = $item['descripcion'];
            } else {
                $nombre_prod = ($id_prod == 0) ? "Avance de Efectivo" : "Artículo ID #".$id_prod;
            }
            
            $cant = isset($item['cantidad']) ? (float)$item['cantidad'] : 1;
            $desc = isset($item['descuento']) ? (float)$item['descuento'] : 0;
            
            $precio_base = 0;
            if (isset($item['precio_usd'])) {
                $precio_base = (float)$item['precio_usd'];
            } elseif (isset($item['precio'])) {
                $precio_base = (float)$item['precio'];
            }
            
            $precio_con_descuento = truncar2($precio_base * (1 - ($desc / 100)));
            
            $es_precio_fijo_bs = false;
            $total_factura_usd = isset($venta['total']) ? (float)$venta['total'] : 0;
            
            if (isset($item['tipo_precio']) && $item['tipo_precio'] == 'FIJO BS') {
                $es_precio_fijo_bs = true;
            } elseif ($precio_con_descuento > $total_factura_usd && $total_factura_usd > 0) {
                $es_precio_fijo_bs = true;
            }
            
            if ($es_precio_fijo_bs) {
                $precio_bs = truncar2($precio_con_descuento);
                $precio_usd_final = ($tasa > 0) ? truncar2($precio_bs / $tasa) : 0;
                $total_linea_bs = truncar2($precio_bs * $cant);
            } else {
                $precio_usd_final = truncar2($precio_con_descuento);
                $precio_bs = truncar2($precio_usd_final * $tasa);
                $total_linea_bs = truncar2(($precio_usd_final * $tasa) * $cant);
            }
            
            $nombre_prod_clean = mysqli_real_escape_string($conexion, $nombre_prod);
            
            $sql_congelar = "INSERT INTO facturas_estaticas_items 
                            (id_venta, id_producto, descripcion, cantidad, precio_usd, precio_bs, total_linea_bs) 
                            VALUES 
                            ($id_venta, $id_prod, '$nombre_prod_clean', $cant, $precio_usd_final, $precio_bs, $total_linea_bs)";
            mysqli_query($conexion, $sql_congelar);
        }
    }
}

// MODIFICACIÓN CLAVE: Agrupar y seleccionar el registro con menor cantidad para evitar filas duplicadas tras devoluciones
$sql_estatico_unico = "SELECT id_venta, id_producto, descripcion, MIN(cantidad) as cantidad, precio_usd, precio_bs, MIN(total_linea_bs) as total_linea_bs 
                       FROM facturas_estaticas_items 
                       WHERE id_venta = $id_venta 
                       GROUP BY id_producto, descripcion";
$res_items_visual = mysqli_query($conexion, $sql_estatico_unico);

ob_clean(); 
?>

<?php if (isset($venta['estado']) && $venta['estado'] === 'DEVUELTA'): ?>
<div class="position-relative mb-3">
    <div class="alert alert-danger border-danger text-center p-3 mb-0" style="background-color: #fff5f5; border-width: 2px; border-style: dashed;">
        <h4 class="text-danger fw-bold m-0" style="letter-spacing: 2px;">
            <i class="fa-solid fa-ban me-2"></i> FACTURA CANCELADA / DEVUELTA
        </h4>
        <small class="text-dark fw-medium">Todos los artículos de esta factura fueron devueltos al inventario.</small>
    </div>
</div>
<?php endif; ?>

<div class="row mb-3 g-2 bg-light p-3 rounded-3" style="font-size: 13px;">
    <div class="col-6">
        <span class="text-muted d-block">Cliente:</span>
        <strong class="text-dark"><?php echo htmlspecialchars($venta['cliente'] ?? 'Público General'); ?></strong>
    </div>
    <div class="col-6 text-end">
        <span class="text-muted d-block">Método de Pago:</span>
        <span class="badge bg-secondary text-white fw-semibold"><?php echo htmlspecialchars($venta['metodo_pago'] ?? 'No especificado'); ?></span>
    </div>
    <div class="col-6 mt-2">
        <span class="text-muted d-block">Vendedor:</span>
        <span class="text-dark"><i class="fa-solid fa-user small me-1 text-muted"></i> <?php echo htmlspecialchars($venta['usuario'] ?? 'root'); ?></span>
    </div>
    <div class="col-6 mt-2 text-end">
        <span class="text-muted d-block">Fecha de Emisión:</span>
        <span class="text-dark"><?php echo isset($venta['fecha']) ? date('d/m/Y h:i A', strtotime($venta['fecha'])) : '--/--/----'; ?></span>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-sm table-striped align-middle" style="font-size: 13px;">
        <thead class="table-dark">
            <tr>
                <th width="15%">Cant.</th>
                <th width="55%">Descripción</th>
                <th width="30%" class="text-end">Total Bs.</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_acumulado_bs = 0;
            $total_acumulado_usd = 0;

            if ($res_items_visual && mysqli_num_rows($res_items_visual) > 0):
                while($item_estatico = mysqli_fetch_assoc($res_items_visual)): 
                    $es_devuelto = ((float)$item_estatico['cantidad'] <= 0 || strpos($item_estatico['descripcion'], 'DEVUELTO') !== false);
                    $linea_bs = $es_devuelto ? 0 : max(0, (float)$item_estatico['total_linea_bs']);
                    
                    if (!$es_devuelto) {
                        $total_acumulado_bs += $linea_bs;
                        $total_acumulado_usd += (float)$item_estatico['precio_usd'] * (float)$item_estatico['cantidad'];
                    }

                    // Limpieza de etiqueta repetida DEVUELTO en la descripción
                    $desc_limpia = str_replace('DEVUELTO', '', $item_estatico['descripcion']);
            ?>
            <tr <?php if ($es_devuelto) echo 'style="background-color: #fff2f2; text-decoration: line-through; color: #9c9c9c;"'; ?>>
                <td class="fw-semibold <?php echo $es_devuelto ? 'text-danger' : 'text-secondary'; ?>">
                    <?php echo number_format(max(0, (float)$item_estatico['cantidad']), 3, ',', '.'); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars(trim($desc_limpia)); ?>
                    <?php if ($es_devuelto): ?>
                        <span class="badge bg-danger ms-1" style="text-decoration: none !important; font-size: 10px; vertical-align: middle;">DEVUELTO</span>
                    <?php endif; ?>
                </td>
                <td class="text-end fw-bold <?php echo $es_devuelto ? 'text-danger' : 'text-dark'; ?>">
                    Bs. <?php echo number_format($linea_bs, 2, ',', '.'); ?>
                </td>
            </tr>
            <?php 
                endwhile;
            else:
            ?>
            <tr>
                <td colspan="3" class="text-center text-muted py-3">
                    <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> No se pudieron recuperar los ítems.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="border-top mt-3 pt-3">
    <?php 
    if ($total_acumulado_usd <= 0 && $tasa > 0 && $total_acumulado_bs > 0) {
        $total_acumulado_usd = truncar2($total_acumulado_bs / $tasa);
    }
    $total_acumulado_bs = truncar2($total_acumulado_bs);
    ?>

    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="text-muted small">Monto neto mercancía (USD):</span>
        <span class="text-success fw-semibold">$ <?php echo number_format($total_acumulado_usd, 2, ',', '.'); ?></span>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="text-muted small">Tasa de Cambio Operacional:</span>
        <span class="text-muted fw-medium">Bs. <?php echo number_format($tasa, 2, ',', '.'); ?></span>
    </div>
    <hr class="my-2 dashed text-muted">
    <div class="d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark">VALOR TOTAL DE LA COMPRA:</span>
        <span class="fs-5 fw-bold text-primary">Bs. <?php echo number_format($total_acumulado_bs, 2, ',', '.'); ?></span>
    </div>

    <!-- Desglose de Pagos Múltiples y Cambio en Bs / USD -->
    <?php 
    $monto_recibido = isset($venta['monto_recibido']) ? (float)$venta['monto_recibido'] : 0;
    $cambio_usd = isset($venta['cambio']) ? (float)$venta['cambio'] : 0;
    $cambio_bs = round($cambio_usd * $tasa, 2);
    ?>

    <?php if ($cambio_usd > 0): ?>
        <div class="mt-2 p-2 rounded bg-light border d-flex justify-content-between align-items-center small">
            <span class="text-danger fw-bold"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Cambio Entregado:</span>
            <span class="fw-bold text-danger">
                Bs. <?php echo number_format($cambio_bs, 2, ',', '.'); ?> ($ <?php echo number_format($cambio_usd, 2, ',', '.'); ?>)
            </span>
        </div>
    <?php endif; ?>

    <?php if ($es_cashea && $inicial_usd > 0): ?>
        <div class="mt-3 p-3 rounded border border-info" style="background-color: #f0fdfa;">
            <h6 class="text-info fw-bold mb-2"><i class="fa-solid fa-calculator me-1"></i> Estructura Transaccional Cashea</h6>
            
            <div class="d-flex justify-content-between text-dark mb-1 small">
                <span>Total Productos original:</span>
                <span class="fw-bold">Bs. <?php echo number_format($total_acumulado_bs, 2, ',', '.'); ?> ($ <?php echo number_format($total_acumulado_usd, 2, ',', '.'); ?>)</span>
            </div>
            
            <div class="d-flex justify-content-between text-success mb-1 small">
                <span>Inicial Cancelada en Caja:</span>
                <strong>Bs. <?php echo number_format(truncar2($inicial_usd * $tasa), 2, ',', '.'); ?> ($ <?php echo number_format($inicial_usd, 2, ',', '.'); ?>)</strong>
            </div>
            
            <div class="d-flex justify-content-between text-danger mb-1 small">
                <span>Monto Financiado (Por la App):</span>
                <span class="fw-bold">Bs. <?php echo number_format(truncar2($financiado_usd * $tasa), 2, ',', '.'); ?> ($ <?php echo number_format($financiado_usd, 2, ',', '.'); ?>)</span>
            </div>

            <?php if (!empty($venta['detalle_pagos_multiples'])): 
                $desglose_pagos = json_decode($venta['detalle_pagos_multiples'], true);
                if (is_array($desglose_pagos) && isset($desglose_pagos['pagos'])):
            ?>
                <hr class="my-2 text-info opacity-25">
                <span class="d-block text-muted small fw-semibold mb-1">Métodos aplicados a la inicial:</span>
                <?php foreach ($desglose_pagos['pagos'] as $pago): 
                    $monto_pago_usd = (float)($pago['monto_usd'] ?? $pago['monto'] ?? 0);
                    $monto_pago_bs = truncar2($monto_pago_usd * $tasa);
                ?>
                    <div class="d-flex justify-content-between align-items-center text-muted ms-2 small">
                        <span>• <?php echo htmlspecialchars($pago['metodo'] ?? 'Pago'); ?>:</span>
                        <span>Bs. <?php echo number_format($monto_pago_bs, 2, ',', '.'); ?> ($ <?php echo number_format($monto_pago_usd, 2, ',', '.'); ?>)</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; endif; ?>
        </div>
    <?php endif; ?>
</div>