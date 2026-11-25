<?php
include 'conexion.php';
include 'validar_licencia.php';
date_default_timezone_set('America/Caracas');

// 1. OBTENER TASA Y TOTALES RÁPIDOS (KPIs)
$res_tasa = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1");
$tasa = mysqli_fetch_assoc($res_tasa)['tasa_dolar'] ?? 1;

$hoy = date('Y-m-d');

// Ventas Hoy
$v_hoy = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT SUM(total) as total FROM ventas WHERE DATE(fecha) = '$hoy'"));
$total_hoy = $v_hoy['total'] ?? 0;

// Créditos Pendientes
$c_pend = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT SUM(deuda_actual) as total FROM clientes_credito"));
$total_credito = $c_pend['total'] ?? 0;

// Stock Bajo (menos de 10 unidades)
$s_bajo = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as total FROM productos WHERE stock < 10"));
$total_bajo = $s_bajo['total'] ?? 0;

// --- DATOS PARA GRÁFICOS ---

// A. Top 5 Productos
$top_query = "SELECT p.nombre, SUM(dv.cantidad) as cant FROM detalle_ventas dv 
              JOIN productos p ON dv.id_producto = p.id 
              GROUP BY dv.id_producto ORDER BY cant DESC LIMIT 5";
$top_res = mysqli_query($conexion, $top_query);
$labels_top = []; $data_top = [];
while($r = mysqli_fetch_assoc($top_res)){ $labels_top[] = $r['nombre']; $data_top[] = $r['cant']; }

// B. Tendencia 7 días
$semana_labels = []; $semana_data = [];
for($i = 6; $i >= 0; $i--){
    $d = date('Y-m-d', strtotime("-$i days"));
    $semana_labels[] = date('d/m', strtotime($d));
    $res = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT SUM(total) as total FROM ventas WHERE DATE(fecha) = '$d'"));
    $semana_data[] = $res['total'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estadísticas del Sistema</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; margin: 0; padding: 20px; }
        
        /* BARRA DE NAVEGACIÓN */
        .nav-container { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background: white; 
            padding: 10px 20px; 
            border-radius: 12px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .nav-buttons { display: flex; gap: 10px; }
        .btn-nav { 
            padding: 8px 16px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 0.85rem; 
            font-weight: 600; 
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        .btn-back:hover { background: #e2e8f0; }
        .btn-primary { background: #4f46e5; color: white; border: none; }
        .btn-primary:hover { background: #4338ca; }

        /* DASHBOARD */
        .grid-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .card-kpi { background: white; padding: 15px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 4px solid #4f46e5; }
        .card-kpi small { color: #64748b; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .card-kpi h3 { margin: 5px 0; font-size: 1.3rem; color: #1e293b; }
        
        .grid-charts { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .card-chart { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .card-chart h4 { margin: 0 0 15px 0; font-size: 0.85rem; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
        
        @media (max-width: 768px) { .grid-charts { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <nav class="nav-container">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="background: #4f46e5; color: white; padding: 8px; border-radius: 8px;">📊</div>
            <h2 style="margin: 0; font-size: 1.2rem; color: #0f172a;">Estadísticas</h2>
        </div>
        
        <div class="nav-buttons">
            <a href="ventas.php" class="btn-nav btn-back">🛒 Ventas</a>
            <a href="inventario.php" class="btn-nav btn-back">📦 Inventario</a>
            <a href="configuracion.php" class="btn-nav btn-primary">⚙️ Configuración</a>
        </div>
    </nav>

    <div class="grid-kpi">
        <div class="card-kpi">
            <small>Ventas Hoy</small>
            <h3>$ <?php echo number_format($total_hoy, 2); ?></h3>
            <span style="font-size: 0.75rem; color: #10b981; font-weight: bold;">Bs. <?php echo number_format($total_hoy * $tasa, 2, ',', '.'); ?></span>
        </div>
        <div class="card-kpi" style="border-color: #10b981;">
            <small>Cuentas por Cobrar</small>
            <h3>$ <?php echo number_format($total_credito, 2); ?></h3>
        </div>
        <div class="card-kpi" style="border-color: #f59e0b;">
            <small>Tasa de Referencia</small>
            <h3><?php echo number_format($tasa, 2, ',', '.'); ?> <small style="font-size: 10px;">Bs</small></h3>
        </div>
        <div class="card-kpi" style="border-color: #ef4444;">
            <small>Productos en Alerta</small>
            <h3><?php echo $total_bajo; ?> <small style="font-size: 10px;">Items</small></h3>
        </div>
    </div>

    <div class="grid-charts">
        
        <div class="card-chart">
            <h4>🔥 Top 5 más vendidos</h4>
            <canvas id="chartTop" height="150"></canvas>
        </div>

        <div class="card-chart">
            <h4>📈 Flujo de Ventas (Semanal)</h4>
            <canvas id="chartSemana" height="150"></canvas>
        </div>

        <div class="card-chart">
            <h4>📁 Distribución por Departamento</h4>
            <div style="max-width: 250px; margin: auto;">
                <canvas id="chartDepto"></canvas>
            </div>
        </div>

        <div class="card-chart">
            <h4>⚠️ Stock Crítico</h4>
            <div style="max-height: 200px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <?php
                    $res_critico = mysqli_query($conexion, "SELECT nombre, stock FROM productos WHERE stock < 10 ORDER BY stock ASC LIMIT 6");
                    while($c = mysqli_fetch_assoc($res_critico)):
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 10px 0; color: #334155;"><?php echo $c['nombre']; ?></td>
                        <td style="text-align: right;"><span style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 10px; font-weight: bold;"><?php echo $c['stock']; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>

    </div>

    <script>
    // Gráfico Top 5 (Barras Horizontales)
    new Chart(document.getElementById('chartTop'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels_top); ?>,
            datasets: [{
                data: <?php echo json_encode($data_top); ?>,
                backgroundColor: '#4f46e5',
                borderRadius: 6
            }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
    });

    // Gráfico Semanal (Líneas)
    new Chart(document.getElementById('chartSemana'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($semana_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($semana_data); ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    // Gráfico Departamentos (Dona)
    new Chart(document.getElementById('chartDepto'), {
        type: 'doughnut',
        data: {
            labels: ['Víveres', 'Lácteos', 'Limpieza', 'Otros'],
            datasets: [{
                data: [50, 20, 15, 15],
                backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#94a3b8']
            }]
        },
        options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } } }
    });
    </script>
</body>
</html>