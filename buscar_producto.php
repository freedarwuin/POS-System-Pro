<?php
include 'conexion.php';
session_start();

$query = mysqli_real_escape_string($conexion, $_POST['query']);

// Obtener la tasa para los cálculos de Bs
$res_tasa = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1");
$tasa_actual = mysqli_fetch_assoc($res_tasa)['tasa_dolar'] ?? 1;

$sql = "SELECT p.*, d.nombre as nombre_depto 
        FROM productos p 
        LEFT JOIN departamentos d ON p.id_departamento = d.id 
        WHERE p.nombre LIKE '%$query%' OR p.codigo_barras LIKE '%$query%'
        ORDER BY p.id DESC";

$res = mysqli_query($conexion, $sql);

while($p = mysqli_fetch_assoc($res)) {
    $moneda_p = $p['moneda'] ?? 'USD';
    if ($moneda_p === 'VES') {
        $precio_usd_calc = $p['precio'] / $tasa_actual;
        $precio_bs_calc = $p['precio'];
        $badge_moneda = '<span class="badge-moneda">FIJO BS</span>';
    } else {
        $precio_usd_calc = $p['precio'];
        $precio_bs_calc = $p['precio'] * $tasa_actual;
        $badge_moneda = '';
    }

    echo '<tr class="fila">
        <td>
            <b style="font-size:14px;">'. $p['nombre'] .'</b> ';
            if($p['usa_inventario'] == 0) echo '<span class="badge-servicio">SERVICIO</span> ';
            echo $badge_moneda . '<br>
            <small style="color:var(--blue); font-weight:600;">'. ($p['nombre_depto'] ?? 'General') .'</small> | 
            <small style="color:var(--text-sub)">'. $p['codigo_barras'] .'</small>
        </td>
        <td><span class="badge-tipo">'. $p['tipo_venta'] .'</span></td>
        <td style="color:var(--green); font-weight:bold;">';
            if($moneda_p === 'VES') {
                echo '<small style="color:gray;">~$'. number_format($precio_usd_calc, 2) .'</small>';
            } else {
                echo '$'. number_format($precio_usd_calc, 2);
            }
        echo '</td>
        <td style="color:var(--blue); font-weight:bold;">
            Bs '. number_format($precio_bs_calc, 2, ',', '.') .'
        </td>
        <td>';
            if($p['usa_inventario'] == 1) {
                echo '<b>'. $p['stock'] .'</b> <small>'. $p['unidad_medida'] .'</small>';
                if($p['stock'] <= 5) echo '<br><span class="badge-stock">STOCK BAJO</span>';
            } else {
                echo '<span style="font-size: 18px; color: var(--slate);">∞</span> <small>Ilimitado</small>';
            }
        echo '</td>
        <td style="display:flex; justify-content:center; gap:8px;">
            <button onclick="verDetalles(\''.$p['codigo_barras'].'\',\''.addslashes($p['nombre']).'\',\''.$p['precio_costo'].'\',\''.number_format($precio_usd_calc,2,'.','').'\',\''.$p['stock'].'\',\''.$p['unidad_medida'].'\',\''.$p['tipo_venta'].'\',\''.number_format($precio_bs_calc,2,',','.').'\', \''.$moneda_p.'\', \''.$p['precio_mayor'].'\', \''.$p['cantidad_mayor'].'\', \''.$p['precio_bulto'].'\', \''.$p['cantidad_bulto'].'\', \''.$tasa_actual.'\')" class="btn-action" style="background:var(--blue)">👁️</button>
            
            <button onclick="abrirE(\''.$p['id'].'\',\''.$p['codigo_barras'].'\',\''.addslashes($p['nombre']).'\',\''.$p['precio_costo'].'\',\''.$p['precio'].'\',\''.$p['stock'].'\',\''.$p['tipo_venta'].'\',\''.$p['unidad_medida'].'\',\''.$p['id_departamento'].'\', \''.$moneda_p.'\', \''.$p['precio_mayor'].'\', \''.$p['cantidad_mayor'].'\', \''.$p['precio_bulto'].'\', \''.$p['cantidad_bulto'].'\')" class="btn-action" style="background:var(--amber)">✏️</button>
            
            <a href="?eliminar='.$p['id'].'" class="btn-action" style="background:#000;" onclick="return confirm(\'¿Eliminar producto?\')">🗑️</a>
        </td>
    </tr>';
}
?>