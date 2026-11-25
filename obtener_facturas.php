<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario']) || !isset($_GET['id'])) { exit("No autorizado"); }

// CORREGIDO: Añadido el guion bajo "_" a la variable global supergoblal de PHP
$id_prov = (int)$_GET['id'];

// Consultamos la tasa actual para cálculos informativos en pantalla
$res_tasa = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1");
$tasa_actual = mysqli_fetch_assoc($res_tasa)['tasa_dolar'] ?? 1;

$stmt = mysqli_prepare($conexion, "SELECT * FROM facturas_proveedores WHERE id_proveedor = ? ORDER BY id DESC");
mysqli_stmt_bind_param($stmt, "i", $id_prov);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($resultado) == 0) {
    echo '<p style="color:var(--text-sub); text-align:center; padding:20px;">Este proveedor no registra despachos aún.</p>';
} else {
    while ($f = mysqli_fetch_assoc($resultado)) {
        // Colores de los estados correspondientes a tu paleta
        $color_estado = "var(--red)"; 
        if($f['estado'] === 'Abonada') $color_estado = "var(--amber)";
        if($f['estado'] === 'Pagada') $color_estado = "var(--green)";

        echo '<div style="background:#f8fafc; padding:15px; border-radius:12px; margin-bottom:15px; border:1px solid var(--border);">';
        echo '<span class="badge-tipo" style="background:'.$color_estado.'; color:white; font-weight:700; float:right;">' . $f['estado'] . '</span>';
        echo '<strong>Factura: #' . htmlspecialchars($f['numero_factura']) . '</strong><br>';
        echo '<small style="color:var(--text-sub);">Fecha: ' . date('d-m-Y', strtotime($f['fecha_factura'])) . '</small>';
        
        echo '<div style="margin:10px 0; background:white; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">';
        echo '<b>Monto Total:</b> $' . number_format($f['monto_total'], 2) . '<br>';
        echo '<b style="color:var(--red);">Saldo Pendiente:</b> $' . number_format($f['saldo_pendiente'], 2) . ' <small style="color:gray;">(~Bs ' . number_format($f['saldo_pendiente'] * $tasa_actual, 2, ',', '.') . ')</small>';
        echo '</div>';

        echo '<p style="margin:5px 0; font-size:12px; color:var(--text-main);"><b>Mercancía:</b><br>' . nl2br(htmlspecialchars($f['descripcion_mercancia'])) . '</p>';
        
        // --- SECCIÓN DE ABONOS HISTÓRICOS ---
        $stmt_ab = mysqli_prepare($conexion, "SELECT * FROM abonos_proveedores WHERE id_factura = ? ORDER BY id ASC");
        mysqli_stmt_bind_param($stmt_ab, "i", $f['id']);
        mysqli_stmt_execute($stmt_ab);
        $res_ab = mysqli_stmt_get_result($stmt_ab);

        if(mysqli_num_rows($res_ab) > 0) {
            echo '<div style="margin-top:10px; background:#f1f5f9; padding:8px; border-radius:6px; font-size:11px;">';
            echo '<b>Historial de Pagos parciales:</b><br>';
            while($ab = mysqli_fetch_assoc($res_ab)) {
                echo '• ' . date('d/m/Y', strtotime($ab['fecha_abono'])) . ' - Mencionó ' . $ab['metodo_pago'] . ': ';
                if($ab['monto_abono_usd'] > 0) echo '$' . number_format($ab['monto_abono_usd'], 2) . ' ';
                if($ab['monto_abono_bs'] > 0) echo 'Bs ' . number_format($ab['monto_abono_bs'], 2, ',', '.') . ' (Tasa: ' . $ab['tasa_cambio'] . ')';
                if($ab['nota']) echo ' <i>('.htmlspecialchars($ab['nota']).')</i>';
                echo '<br>';
            }
            echo '</div>';
        }

        echo '<div style="margin-top:10px; display:flex; gap:8px;">';
        if ($f['saldo_pendiente'] > 0) {
            echo '<button onclick="abrirFormAbono(' . $f['id'] . ', \'' . $f['numero_factura'] . '\', ' . $f['saldo_pendiente'] . ')" class="btn" style="background:var(--green); padding:4px 8px; font-size:11px;">💵 Abonar / Pagar</button>';
        }
        if ($f['archivo_factura']) {
            echo '<button onclick="verFacturaCompleta(\'' . $f['archivo_factura'] . '\')" class="btn" style="background:var(--slate); padding:4px 8px; font-size:11px;">👁️ Ver Soporte</button>';
        }
        echo '</div>';
        
        echo '</div>';
    }
}
?>