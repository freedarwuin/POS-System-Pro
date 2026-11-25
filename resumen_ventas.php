<?php
include 'conexion.php';

// Filtros por defecto (Hoy)
$fecha_desde = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-d');
$fecha_hasta = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-d');

// Consulta avanzada que une ventas con productos vendidos
$query = "SELECT v.id, v.fecha, v.total, 
          GROUP_CONCAT(CONCAT('<b>', dv.cantidad, 'x</b> ', p.nombre) SEPARATOR '<br>') as lista_productos
          FROM ventas v
          JOIN detalle_ventas dv ON v.id = dv.id_venta
          JOIN productos p ON dv.id_producto = p.id
          WHERE DATE(v.fecha) BETWEEN '$fecha_desde' AND '$fecha_hasta'
          GROUP BY v.id 
          ORDER BY v.fecha DESC";

$resultado = mysqli_query($conexion, $query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas Detallado</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; margin: 0; padding: 30px; }
        .container { max-width: 1100px; margin: auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #1e293b; font-size: 28px; }
        
        /* Estilo del Formulario de Filtros */
        .seccion-filtros { background: #f1f5f9; padding: 20px; border-radius: 10px; margin-bottom: 30px; display: flex; gap: 20px; align-items: flex-end; }
        .campo-filtro { display: flex; flex-direction: column; gap: 5px; }
        .campo-filtro label { font-size: 12px; font-weight: bold; color: #64748b; text-transform: uppercase; }
        .campo-filtro input { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        
        .btn-filtrar { background: #2563eb; color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; height: 38px; }
        .btn-filtrar:hover { background: #1d4ed8; }

        /* Tabla de Reporte */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; background: #f8fafc; color: #475569; font-size: 13px; border-bottom: 2px solid #e2e8f0; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 15px; }
        
        .id-venta { font-weight: bold; color: #2563eb; }
        .monto-total { font-size: 18px; font-weight: 800; color: #1e293b; }
        
        /* Caja de Gran Total */
        .caja-resumen-final { margin-top: 40px; background: #1e293b; color: white; padding: 25px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; }
        .total-valor { font-size: 32px; font-weight: 800; color: #10b981; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>📊 Resumen Detallado</h2>
        <a href="ventas.php" style="background: #e2e8f0; padding: 10px 20px; border-radius: 6px; text-decoration: none; color: #475569; font-weight: bold;">← Volver al POS</a>
    </div>

    <form class="seccion-filtros" method="GET">
        <div class="campo-filtro">
            <label>Fecha Inicial</label>
            <input type="date" name="desde" value="<?= $fecha_desde ?>">
        </div>
        <div class="campo-filtro">
            <label>Fecha Final</label>
            <input type="date" name="hasta" value="<?= $fecha_hasta ?>">
        </div>
        <button type="submit" class="btn-filtrar">Generar Reporte</button>
        <a href="resumen_ventas.php" style="color: #64748b; font-size: 13px; text-decoration: none; margin-bottom: 10px;">Limpiar filtros</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>Factura</th>
                <th>Fecha / Hora</th>
                <th>Productos Vendidos</th>
                <th>Monto Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $gran_total_acumulado = 0;
            if(mysqli_num_rows($resultado) > 0):
                while($v = mysqli_fetch_assoc($resultado)): 
                    $gran_total_acumulado += $v['total'];
            ?>
            <tr>
                <td><span class="id-venta">#<?= $v['id'] ?></span></td>
                <td style="color: #64748b; font-size: 14px;"><?= date('d/m/Y h:i A', strtotime($v['fecha'])) ?></td>
                <td style="color: #334155; line-height: 1.5;"><?= $v['lista_productos'] ?></td>
                <td class="monto-total">$ <?= number_format($v['total'], 2) ?></td>
            </tr>
            <?php endwhile; 
            else: ?>
            <tr><td colspan="4" style="text-align: center; padding: 50px; color: #94a3b8;">No hay ventas registradas en el rango seleccionado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="caja-resumen-final">
        <div>
            <span style="text-transform: uppercase; font-size: 12px; letter-spacing: 1px; opacity: 0.7;">Ganancia Total del Periodo</span>
            <div style="font-size: 14px; margin-top: 5px;">Rango: <?= date('d/m/Y', strtotime($fecha_desde)) ?> - <?= date('d/m/Y', strtotime($fecha_hasta)) ?></div>
        </div>
        <div class="total-valor">$ <?= number_format($gran_total_acumulado, 2) ?></div>
    </div>
</div>

</body>
</html>