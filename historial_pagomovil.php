<?php
session_start();
include 'validar_licencia.php';
include 'conexion.php';
date_default_timezone_set('America/Caracas');

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Capturar parámetros de búsqueda
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$fecha_desde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';

// Consulta base
$query = "SELECT rpm.*, v.tasa_momento 
          FROM registro_pago_movil rpm
          LEFT JOIN ventas v ON rpm.id_venta = v.id
          WHERE 1=1";

// Filtro por texto (Referencia o ID de Venta)
if (!empty($busqueda)) {
    $busqueda_escapada = mysqli_real_escape_string($conexion, $busqueda);
    $query .= " AND (rpm.referencia LIKE '%$busqueda_escapada%' 
                OR rpm.id_venta LIKE '%$busqueda_escapada%')";
}

// Filtros por Fecha
if (!empty($fecha_desde)) {
    $fecha_desde_escapada = mysqli_real_escape_string($conexion, $fecha_desde);
    $query .= " AND DATE(rpm.fecha) >= '$fecha_desde_escapada'";
}

if (!empty($fecha_hasta)) {
    $fecha_hasta_escapada = mysqli_real_escape_string($conexion, $fecha_hasta);
    $query .= " AND DATE(rpm.fecha) <= '$fecha_hasta_escapada'";
}

$query .= " ORDER BY rpm.fecha DESC";
$resultado = mysqli_query($conexion, $query);

$hay_filtros = !empty($busqueda) || !empty($fecha_desde) || !empty($fecha_hasta);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Pago Móvil</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; margin: 20px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .table-custom { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table-custom th, .table-custom td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        .table-custom th { background: #1e293b; color: white; }
        .btn-volver { padding: 8px 15px; background: #3b82f6; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; }
        
        /* Contenedor y controles del buscador */
        .search-container { margin-top: 20px; background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .search-box { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .field-group { display: flex; flex-direction: column; gap: 5px; }
        .field-group label { font-size: 12px; font-weight: bold; color: #475569; }
        .input-search, .input-date { 
            padding: 9px 12px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            font-size: 14px; 
            outline: none;
            background: white;
        }
        .input-search { flex: 2; min-width: 220px; }
        .input-date { flex: 1; min-width: 140px; }
        .input-search:focus, .input-date:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .btn-search { padding: 10px 18px; background: #0f172a; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; height: 38px; }
        .btn-clear { padding: 9px 18px; background: #64748b; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; height: 38px; display: inline-flex; align-items: center; box-sizing: border-box; }
    </style>
</head>
<body>

<div class="card">
    <div style="display:flex; justify-content: space-between; align-items:center;">
        <h2 style="color:#1e293b; margin:0;">📱 Historial de Transacciones - Pago Móvil</h2>
        <a href="index.php" class="btn-volver">⬅ Volver al POS</a>
    </div>

    <!-- Formulario de Filtros con Fecha -->
    <div class="search-container">
        <form method="GET" action="" class="search-box">
            <div class="field-group" style="flex: 2; min-width: 220px;">
                <label>Búsqueda General</label>
                <input 
                    type="text" 
                    name="busqueda" 
                    class="input-search" 
                    placeholder="🔍 Referencia o ID Venta..." 
                    value="<?php echo htmlspecialchars($busqueda); ?>"
                >
            </div>

            <div class="field-group">
                <label>Desde</label>
                <input 
                    type="date" 
                    name="fecha_desde" 
                    class="input-date" 
                    value="<?php echo htmlspecialchars($fecha_desde); ?>"
                >
            </div>

            <div class="field-group">
                <label>Hasta</label>
                <input 
                    type="date" 
                    name="fecha_hasta" 
                    class="input-date" 
                    value="<?php echo htmlspecialchars($fecha_hasta); ?>"
                >
            </div>

            <button type="submit" class="btn-search">Filtrar</button>

            <?php if ($hay_filtros): ?>
                <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn-clear">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <table class="table-custom">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha y Hora</th>
                <th>Banco Emisor</th>
                <th>Referencia</th>
                <th>Monto (Bs.)</th>
                <th>Monto ($)</th>
                <th>ID Venta / Factura</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($resultado) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($resultado)): 
                    $tasa = !empty($row['tasa_momento']) && $row['tasa_momento'] > 0 ? $row['tasa_momento'] : 1;
                    $monto_usd = $row['monto_bs'] / $tasa;
                ?>
                <tr>
                    <td>#<?php echo $row['id']; ?></td>
                    <td><?php echo date('d/m/Y h:i A', strtotime($row['fecha'])); ?></td>
                    <td><b><?php echo htmlspecialchars($row['banco_origen']); ?></b></td>
                    <td><code><?php echo htmlspecialchars($row['referencia']); ?></code></td>
                    <td><b>Bs. <?php echo number_format($row['monto_bs'], 2, ',', '.'); ?></b></td>
                    <td style="color:#10b981; font-weight:bold;">$<?php echo number_format($monto_usd, 2, ',', '.'); ?></td>
                    <td><?php echo !empty($row['id_venta']) ? "#".$row['id_venta'] : 'N/A'; ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #64748b;">
                        <?php echo $hay_filtros ? 'No se encontraron resultados con los filtros aplicados.' : 'No hay transacciones de Pago Móvil registradas aún.'; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>