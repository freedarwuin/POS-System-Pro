<?php
session_start();
include 'conexion.php';
@include_once 'validar_licencia.php'; 

// 1. SEGURIDAD
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Admin') { 
    header("Location: login.php"); 
    exit(); 
}

// FUNCIÓN AUXILIAR PARA FORMATO DE STOCK (Sin decimales innecesarios en la vista)
function format_stock($value) {
    return (fmod((float)$value, 1) == 0) ? number_format($value, 0, ',', '.') : number_format($value, 2, ',', '.');
}

// 2. OBTENER TASA ACTUAL
$res_tasa = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1");
$tasa_actual = mysqli_fetch_assoc($res_tasa)['tasa_dolar'] ?? 1;

// 3. GESTIÓN DE FILTROS
$id_depto_filtro = isset($_GET['depto']) && $_GET['depto'] != "" ? (int)$_GET['depto'] : null;

$where_clause = " WHERE p.usa_inventario = 1 AND p.stock > 0 ";
if ($id_depto_filtro) {
    $where_clause .= " AND p.id_departamento = $id_depto_filtro ";
}

// 4. CÁLCULO DE TOTALES (LÓGICA CORREGIDA PARA GRAMOS Y KILOS)
$query_totales = mysqli_query($conexion, "SELECT precio_costo, precio, stock, moneda, unidad_medida FROM productos p $where_clause");

$costo_total_usd = 0;
$venta_total_usd = 0;
$resumen_existencia = []; 

while($row = mysqli_fetch_assoc($query_totales)) {
    $stock = (float)$row['stock'];
    $moneda = $row['moneda'];
    $um = strtoupper($row['unidad_medida'] ?? 'UNID');

    // PASO 1: NORMALIZACIÓN DE UNIDADES PARA EL CÁLCULO EXACTO
    if ($um == 'G' || $um == 'GR' || $um == 'GRAMOS') {
        $cantidad_normalizada = $stock / 1000;
        $unidad_destino = 'KG';
    } else {
        $cantidad_normalizada = $stock;
        $unidad_destino = $um;
    }

    // PASO 2: CONVERSIÓN DE MONEDA A BASE USD
    if ($moneda === 'USD') {
        $c_usd = (float)$row['precio_costo'];
        $v_usd = (float)$row['precio'];
    } else {
        $c_usd = (float)$row['precio_costo'] / $tasa_actual;
        $v_usd = (float)$row['precio'] / $tasa_actual;
    }

    // PASO 3: CÁLCULO USANDO LA CANTIDAD NORMALIZADA (Evita inflar costos)
    $costo_total_usd += ($c_usd * $cantidad_normalizada);
    $venta_total_usd += ($v_usd * $cantidad_normalizada);

    // Sumatoria para el dashboard
    if (!isset($resumen_existencia[$unidad_destino])) {
        $resumen_existencia[$unidad_destino] = 0;
    }
    $resumen_existencia[$unidad_destino] += $cantidad_normalizada;
}

$ganancia_total_usd = $venta_total_usd - $costo_total_usd;

// Conversiones a BS para los totales del dashboard
$costo_total_bs = $costo_total_usd * $tasa_actual;
$venta_total_bs = $venta_total_usd * $tasa_actual;
$ganancia_total_bs = $ganancia_total_usd * $tasa_actual;

// 5. CONSULTA PARA LA TABLA
$productos = mysqli_query($conexion, "SELECT p.*, d.nombre as nombre_depto 
    FROM productos p 
    LEFT JOIN departamentos d ON p.id_departamento = d.id 
    $where_clause 
    ORDER BY p.nombre ASC");

$deptos = mysqli_query($conexion, "SELECT * FROM departamentos ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario</title>
    <style>
        :root { 
            --bg-body: #f1f5f9; --bg-card: #ffffff; --text-main: #1e293b; 
            --text-sub: #64748b; --border: #e2e8f0; --table-head: #f8fafc; 
            --dark: #1e293b; --blue: #2563eb; --green: #10b981; --amber: #f59e0b; 
        }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); margin: 0; padding: 20px; }
        .header-container { background: var(--dark); padding: 15px 25px; color: white; border-radius: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .card { background: var(--bg-card); padding: 20px; border-radius: 15px; border: 1px solid var(--border); text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .card h4 { margin: 0 0 10px 0; color: var(--text-sub); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .monto-usd { font-size: 22px; font-weight: bold; margin-bottom: 5px; }
        .monto-bs { font-size: 13px; color: var(--text-sub); font-weight: 600; }
        .card.costo .monto-usd { color: var(--amber); }
        .card.venta .monto-usd { color: var(--blue); }
        .card.ganancia .monto-usd { color: var(--green); }
        .toolbar { background: var(--bg-card); padding: 15px 20px; border-radius: 15px; margin-bottom: 20px; border: 1px solid var(--border); display: flex; gap: 15px; align-items: center; }
        .toolbar select { padding: 10px; border-radius: 8px; border: 1px solid var(--border); width: 250px; font-weight: 600; }
        .btn { padding: 10px 20px; border-radius: 10px; font-weight: bold; cursor: pointer; border: none; color: white; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; }
        .tabla-container { background: var(--bg-card); border-radius: 15px; border: 1px solid var(--border); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--table-head); padding: 15px; text-align: left; font-size: 11px; color: var(--text-sub); text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 12px 15px; border-bottom: 1px solid var(--border); font-size: 13px; }
        .sub-bs { display: block; font-size: 11px; color: #94a3b8; }
        @media print { .no-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>

    <div class="header-container">
        <h2 style="margin:0;">📊 Reporte de Valoración de Inventario</h2>
        <div class="no-print">
            <a href="inventario.php" class="btn" style="background:rgba(255,255,255,0.1); margin-right:10px;">⬅️ Volver</a>
            <button onclick="window.print()" class="btn" style="background:var(--blue)">🖨️ Imprimir</button>
        </div>
    </div>

    <div class="dashboard">
        <div class="card">
            <h4>Total Físico</h4>
            <div style="font-size: 14px; font-weight: bold; line-height: 1.5;">
                <?php 
                if(empty($resumen_existencia)) echo "Sin stock";
                foreach($resumen_existencia as $unidad => $total) {
                    echo "<div>" . format_stock($total) . " <small>$unidad</small></div>";
                }
                ?>
            </div>
        </div>

        <div class="card costo">
            <h4>Inversión en Stock (Costo)</h4>
            <div class="monto-usd">$<?= number_format($costo_total_usd, 2) ?></div>
            <div class="monto-bs">Bs <?= number_format($costo_total_bs, 2, ',', '.') ?></div>
        </div>

        <div class="card venta">
            <h4>Valor del Inventario (Venta)</h4>
            <div class="monto-usd">$<?= number_format($venta_total_usd, 2) ?></div>
            <div class="monto-bs">Bs <?= number_format($venta_total_bs, 2, ',', '.') ?></div>
        </div>

        <div class="card ganancia">
            <h4>Ganancia Proyectada</h4>
            <div class="monto-usd">+$<?= number_format($ganancia_total_usd, 2) ?></div>
            <div class="monto-bs">Bs <?= number_format($ganancia_total_bs, 2, ',', '.') ?></div>
        </div>
    </div>

    <div class="toolbar no-print">
        <form method="GET" style="display: flex; align-items: center; gap: 15px; width: 100%;">
            <span style="font-weight: bold; font-size: 14px; color: var(--text-sub);">FILTRAR POR DEPARTAMENTO:</span>
            <select name="depto" onchange="this.form.submit()">
                <option value="">-- Todos los Departamentos --</option>
                <?php while($d = mysqli_fetch_assoc($deptos)): ?>
                    <option value="<?= $d['id'] ?>" <?= ($id_depto_filtro == $d['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nombre']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <?php if($id_depto_filtro): ?>
                <a href="reporte_inventario.php" style="color: var(--amber); text-decoration: none; font-size: 12px; font-weight: bold;">[Ver todo]</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="tabla-container">
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Stock</th>
                    <th>Costo Unit.</th>
                    <th>Venta Unit.</th>
                    <th>Inversión Total</th>
                    <th>Venta Total</th>
                </tr>
            </thead>
            <tbody>
                <?php while($p = mysqli_fetch_assoc($productos)): 
                    $m = $p['moneda'];
                    $stock_p = (float)$p['stock'];
                    $um_p = strtoupper($p['unidad_medida'] ?? 'UNID');

                    // APLICAMOS LA MISMA LÓGICA DE NORMALIZACIÓN A LA TABLA
                    if ($um_p == 'G' || $um_p == 'GR' || $um_p == 'GRAMOS') {
                        $cant_norm = $stock_p / 1000;
                    } else {
                        $cant_norm = $stock_p;
                    }

                    if ($m === 'USD') {
                        $c_u = (float)$p['precio_costo']; 
                        $v_u = (float)$p['precio'];
                    } else {
                        $c_u = (float)$p['precio_costo'] / $tasa_actual; 
                        $v_u = (float)$p['precio'] / $tasa_actual;
                    }
                    
                    // CÁLCULO FINAL CON LA CANTIDAD CORREGIDA
                    $inv_t = $c_u * $cant_norm;
                    $ven_t = $v_u * $cant_norm;
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($p['nombre']) ?></strong><br>
                        <small style="color:var(--text-sub)"><?= htmlspecialchars($p['nombre_depto'] ?? 'General') ?></small>
                    </td>
                    <td>
                        <span style="font-weight:bold"><?= format_stock($p['stock']) ?></span> 
                        <small><?= htmlspecialchars($p['unidad_medida']) ?></small>
                    </td>
                    <td>$<?= number_format($c_u, 2) ?></td>
                    <td>$<?= number_format($v_u, 2) ?></td>
                    <td style="color:var(--amber); font-weight:600;">
                        $<?= number_format($inv_t, 2) ?>
                        <span class="sub-bs">Bs <?= number_format($inv_t * $tasa_actual, 2, ',', '.') ?></span>
                    </td>
                    <td style="color:var(--blue); font-weight:600;">
                        $<?= number_format($ven_t, 2) ?>
                        <span class="sub-bs">Bs <?= number_format($ven_t * $tasa_actual, 2, ',', '.') ?></span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</body>
</html>