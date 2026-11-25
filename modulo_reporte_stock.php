<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// 1. OBTENER TASA ACTUAL
$res_tasa = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1");
$reg_tasa = mysqli_fetch_assoc($res_tasa);
$tasa = (float)($reg_tasa['tasa_dolar'] ?? 1);

// 2. CONSULTA SQL (STOCK DIRECTO COMO KILOS/UNIDADES)
$sql = "SELECT 
            IFNULL(d.nombre, 'General') AS departamento,
            SUM(CASE WHEN p.tipo_venta = 'unidad' THEN p.stock ELSE 0 END) AS total_unidades,
            SUM(CASE WHEN p.tipo_venta = 'granel' THEN p.stock ELSE 0 END) AS total_kg,
            SUM(CASE 
                WHEN p.usa_inventario = 1 AND p.stock > 0 THEN p.stock * p.precio_costo 
                ELSE 0 END) AS inversion_neta_usd,
            SUM(CASE 
                WHEN p.usa_inventario = 1 AND p.stock > 0 THEN 
                    p.stock * (CASE WHEN p.moneda = 'VES' THEN (p.precio / $tasa) ELSE p.precio END)
                ELSE 0 END) AS valor_venta_usd
        FROM productos p
        LEFT JOIN departamentos d ON p.id_departamento = d.id
        GROUP BY d.id, d.nombre";

$resultado = mysqli_query($conexion, $sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario - POS SYSTEM PRO</title>
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-bg: #f4f7f6;
            --accent-color: #3498db;
        }

        body { font-family: 'Segoe UI', sans-serif; background-color: var(--primary-bg); margin: 0; display: flex; }

        /* SIDEBAR STYLE */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: #2c3e50;
            color: white;
            position: fixed;
            padding: 20px;
            box-sizing: border-box;
        }

        .sidebar h2 { font-size: 1.2rem; text-align: center; margin-bottom: 30px; border-bottom: 1px solid #34495e; padding-bottom: 10px; }

        .nav-link {
            display: block;
            color: #bdc3c7;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: 0.3s;
        }

        .nav-link:hover { background: #34495e; color: white; }
        .nav-link.active { background: var(--accent-color); color: white; }

        /* MAIN CONTENT AREA */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 40px;
            box-sizing: border-box;
        }

        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }

        /* TABLES & UI */
        h2.title { margin-top: 0; color: #2c3e50; display: flex; align-items: center; justify-content: space-between; }
        .tasa-tag { font-size: 0.9rem; background: #e8f4fd; color: #2980b9; padding: 5px 15px; border-radius: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #f8f9fa; color: #7f8c8d; padding: 15px; text-align: left; border-bottom: 2px solid #dee2e6; text-transform: uppercase; font-size: 0.85rem; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: #2c3e50; }

        .total-row { background: #2c3e50; color: white; font-weight: bold; }
        .total-row td { color: white; border: none; }
        .val-inversion { color: #e74c3c; font-weight: bold; }
        .val-venta { color: #2980b9; }
        .val-ganancia { color: #27ae60; font-weight: bold; }
    </style>
</head>
<body>

<aside class="sidebar">
    <h2>POS SYSTEM PRO</h2>
    <nav>
        <a href="ventas.php" class="nav-link">🛒 Ventas</a>
        <a href="inventario.php" class="nav-link">📦 Inventario</a>
        <a href="facturas.php" class="nav-link">📄 Facturas</a>
        <a href="corte.php" class="nav-link">📊 Corte</a>
        <a href="estadisticas.php" class="nav-link">📊 Estadisticas</a>
        <a href="historial.php" class="nav-link">📋 HISTORIAL</a>
        
        <a href="creditos.php" class="nav-link">💳 Créditos</a>
        <a href="mercancia_departamentos.php" class="nav-link">📁 Departamentos</a>
        <a href="reporte_inventario.php" class="nav-link active">📁 Reporte de Inventario</a>
        <a href="configuracion.php" class="nav-link">⚙️ Ajustes</a>
        <a href="logout.php" class="nav-link" style="margin-top: 40px; color: #ff5252; font-weight: 600;">🚪 Salir</a>
    </nav>
</aside>

<main class="main-content">
    <div class="container">
        <h2 class="title">
            Resumen de Valorización de Inventario
            <span class="tasa-tag">Tasa: 1$ = <?= number_format($tasa, 2) ?> Bs</span>
        </h2>

        <table>
            <thead>
                <tr>
                    <th>Departamento</th>
                    <th>Unidades</th>
                    <th>Stock (Kg)</th>
                    <th class="text-right">Inversión Neta</th>
                    <th class="text-right">PVP Proyectado</th>
                    <th class="text-right">Ganancia Bruta</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $g_inv = 0; $g_venta = 0;
                while($row = mysqli_fetch_assoc($resultado)): 
                    $ganancia = $row['valor_venta_usd'] - $row['inversion_neta_usd'];
                    $g_inv += $row['inversion_neta_usd'];
                    $g_venta += $row['valor_venta_usd'];
                ?>
                <tr>
                    <td><strong><?= $row['departamento'] ?></strong></td>
                    <td><?= number_format($row['total_unidades'], 0) ?></td>
                    <td><?= number_format($row['total_kg'], 2) ?> Kg</td>
                    <td class="text-right val-inversion">$<?= number_format($row['inversion_neta_usd'], 2) ?></td>
                    <td class="text-right val-venta">$<?= number_format($row['valor_venta_usd'], 2) ?></td>
                    <td class="text-right val-ganancia">$<?= number_format($ganancia, 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">TOTALES:</td>
                    <td class="text-right">$<?= number_format($g_inv, 2) ?></td>
                    <td class="text-right">$<?= number_format($g_venta, 2) ?></td>
                    <td class="text-right">$<?= number_format($g_venta - $g_inv, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</main>

</body>
</html>