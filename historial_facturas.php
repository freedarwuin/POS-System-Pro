<?php
session_start();
include 'conexion.php';

// Si es una petición AJAX, devolvemos las filas de la tabla, paginación y métricas
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $por_pagina = 10;
    $pagina = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    if ($pagina < 1) $pagina = 1;
    $inicio = ($pagina - 1) * $por_pagina;

    $buscar = isset($_GET['buscar']) ? mysqli_real_escape_string($conexion, trim($_GET['buscar'])) : "";
    $metodo = isset($_GET['metodo']) ? mysqli_real_escape_string($conexion, trim($_GET['metodo'])) : "";

    $where_clauses = [];
    if (!empty($buscar)) {
        $where_clauses[] = "(v.id LIKE '%$buscar%' OR v.cliente LIKE '%$buscar%')";
    }
    if (!empty($metodo)) {
        $where_clauses[] = "v.metodo_pago = '$metodo'";
    }

    $where_string = "";
    if (count($where_clauses) > 0) {
        $where_string = "WHERE " . implode(" AND ", $where_clauses);
    }

    // Contar total para paginar
    $sql_total = "SELECT COUNT(*) as total FROM ventas v $where_string";
    $res_total = mysqli_query($conexion, $sql_total);
    $total_registros = mysqli_fetch_assoc($res_total)['total'];
    $total_paginas = ceil($total_registros / $por_pagina);

    // Consulta de ventas paginadas
    $sql_ventas = "SELECT v.*, 
                          SUM(f.total_linea_bs) as total_real_estatico_bs,
                          SUM(f.cantidad * f.precio_usd) as total_real_estatico_usd
                   FROM ventas v
                   LEFT JOIN facturas_estaticas_items f ON v.id = f.id_venta
                   $where_string
                   GROUP BY v.id
                   ORDER BY v.id DESC 
                   LIMIT $inicio, $por_pagina";
    $res_ventas = mysqli_query($conexion, $sql_ventas);

    $html_rows = "";
    $acumulado_usd_pagina = 0;
    $acumulado_bs_pagina = 0;

    if (mysqli_num_rows($res_ventas) > 0) {
        while ($v = mysqli_fetch_assoc($res_ventas)) {
            $id_venta = (int)$v['id'];
            $tasa_historica = (float)$v['tasa_momento'];
            
            // Migración a tabla estática si aplica
            if (empty($v['total_real_estatico_bs']) || (float)$v['total_real_estatico_bs'] <= 0) {
                $sql_origen = "SELECT dv.*, p.nombre FROM detalle_ventas dv LEFT JOIN productos p ON dv.id_producto = p.id WHERE dv.id_venta = $id_venta";
                $res_origen = mysqli_query($conexion, $sql_origen);
                if (!$res_origen) {
                    $sql_origen = "SELECT dv.*, p.nombre FROM detalle_ventas dv LEFT JOIN productos p ON dv.id_producto = p.id WHERE dv.id_ventas = $id_venta";
                    $res_origen = mysqli_query($conexion, $sql_origen);
                }
                
                $suma_calculated_bs = 0;
                $suma_calculated_usd = 0;
                
                if ($res_origen && mysqli_num_rows($res_origen) > 0) {
                    while ($item = mysqli_fetch_assoc($res_origen)) {
                        $id_prod = isset($item['id_producto']) ? (int)$item['id_producto'] : 0;
                        $nombre_prod = !empty($item['nombre']) ? $item['nombre'] : (($id_prod == 0) ? "Avance" : "Artículo #".$id_prod);
                        $cant = isset($item['cantidad']) ? (float)$item['cantidad'] : 1;
                        
                        $precio_usd_final = round((isset($item['precio']) ? (float)$item['precio'] : 0) * (1 - ((isset($item['descuento']) ? (float)$item['descuento'] : 0) / 100)), 2);
                        $precio_bs = round($precio_usd_final * $tasa_historica, 2);
                        $total_linea_bs = round($precio_bs * $cant, 2);
                        
                        $suma_calculated_bs += $total_linea_bs;
                        $suma_calculated_usd += round($precio_usd_final * $cant, 2);
                        
                        $nombre_clean = mysqli_real_escape_string($conexion, $nombre_prod);
                        mysqli_query($conexion, "INSERT INTO facturas_estaticas_items (id_venta, id_producto, descripcion, cantidad, precio_usd, precio_bs, total_linea_bs) VALUES ($id_venta, $id_prod, '$nombre_clean', $cant, $precio_usd_final, $precio_bs, $total_linea_bs)");
                    }
                }
                
                $total_usd_fijo = $suma_calculated_usd > 0 ? $suma_calculated_usd : (float)$v['total'];
                $total_bs_fijo = $suma_calculated_bs > 0 ? $suma_calculated_bs : round($total_usd_fijo * $tasa_historica, 2);
            } else {
                $total_bs_fijo = (float)$v['total_real_estatico_bs'];
                $total_usd_fijo = (float)$v['total_real_estatico_usd'];
            }

            if($total_usd_fijo <= 0) $total_usd_fijo = (float)$v['total'];
            if($total_bs_fijo <= 0) $total_bs_fijo = round($total_usd_fijo * $tasa_historica, 2);

            // Interceptar Cashea
            $info_cashea_adicional = "";
            if (isset($v['metodo_pago']) && $v['metodo_pago'] === 'Cashea' && !empty($v['detalle_pagos_multiples'])) {
                $datos_cashea = json_decode($v['detalle_pagos_multiples'], true);
                if ($datos_cashea) {
                    $inicial_usd = isset($datos_cashea['inicial_usd']) ? (float)$datos_cashea['inicial_usd'] : 0;
                    $financiado_usd = isset($datos_cashea['financiamiento_cashea_usd']) ? (float)$datos_cashea['financiamiento_cashea_usd'] : 0;
                    
                    $total_usd_fijo = $inicial_usd;
                    $total_bs_fijo = round($inicial_usd * $tasa_historica, 2);

                    $info_cashea_adicional = "<br><span class='badge bg-warning text-dark border mt-1' style='font-size:10px;'><i class='fa-solid fa-clock-history me-1'></i>Financ: $".number_format($financiado_usd, 2, ',', '.')."</span>";
                }
            }

            $acumulado_usd_pagina += $total_usd_fijo;
            $acumulado_bs_pagina += $total_bs_fijo;

            // Mapeo dinámico de badges según método
            $badge_style = "bg-secondary text-white";
            $metodo_str = strtolower($v['metodo_pago']);
            if (strpos($metodo_str, 'efectivo') !== false) $badge_style = "bg-success-subtle text-success border border-success-subtle";
            elseif (strpos($metodo_str, 'punto') !== false) $badge_style = "bg-primary-subtle text-primary border border-primary-subtle";
            elseif (strpos($metodo_str, 'cashea') !== false) $badge_style = "bg-warning-subtle text-dark border border-warning-subtle";
            elseif (strpos($metodo_str, 'crédito') !== false || strpos($metodo_str, 'credito') !== false) $badge_style = "bg-danger-subtle text-danger border border-danger-subtle";

            $fecha_formateada = date('d/m/Y h:i A', strtotime($v['fecha']));
            $html_rows .= "<tr>
                <td><span class='fw-bold text-dark font-monospace'>#{$id_venta}</span></td>
                <td><div class='d-flex align-items-center gap-1 text-secondary fs-7'><i class='fa-regular fa-calendar-alt me-1 text-primary opacity-75'></i> {$fecha_formateada}</div></td>
                <td><div class='fw-semibold text-dark'>".htmlspecialchars($v['cliente'])."</div></td>
                <td><span class='badge rounded-pill px-3 py-1 fs-7 {$badge_style}'>".htmlspecialchars($v['metodo_pago'])."</span>{$info_cashea_adicional}</td>
                <td><span class='text-muted fs-7 fw-medium'>Bs. ".number_format($tasa_historica, 2, ',', '.')."</span></td>
                <td><span class='fw-bold text-emerald fs-6'>$ ".number_format($total_usd_fijo, 2, ',', '.')."</span></td>
                <td><span class='fw-bold text-slate fs-6'>Bs. ".number_format($total_bs_fijo, 2, ',', '.')."</span></td>
                <td class='text-center'>
                    <div class='btn-group shadow-sm rounded-3' role='group'>
                        <button type='button' class='btn btn-sm btn-light border-0 hover-lift' onclick='verDetalleFactura({$id_venta})' title='Ver Detalles'><i class='fa-regular fa-eye text-primary'></i></button>
                        <button type='button' class='btn btn-sm btn-light border-0 hover-lift' onclick='abrirModalDevolucion({$id_venta})' title='Devolución Específica'><i class='fa-solid fa-rotate-left text-warning'></i></button>
                        <button type='button' class='btn btn-sm btn-light border-0 hover-lift' onclick='reimprimirFactura({$id_venta})' title='Imprimir'><i class='fa-solid fa-print text-secondary'></i></button>
                    </div>
                </td>
            </tr>";
        }
    } else {
        $html_rows .= "<tr><td colspan='8' class='text-center py-5 text-muted'><i class='fa-regular fa-folder-open fa-3x mb-3 text-secondary opacity-50 d-block'></i><span class='fs-6 fw-medium'>No se encontraron registros con los filtros seleccionados.</span></td></tr>";
    }

    $html_pagination = "";
    if ($total_paginas > 1) {
        $html_pagination .= '<ul class="pagination pagination-sm m-0 gap-1">';
        $disabled_prev = ($pagina <= 1) ? 'disabled' : '';
        $html_pagination .= "<li class='page-item {$disabled_prev}'><a class='page-link rounded-2' href='#' onclick='cambiarPagina(".($pagina - 1)."); return false;'><i class='fa-solid fa-chevron-left fs-7'></i></a></li>";
        
        $rango = 2;
        for ($i = 1; $i <= $total_paginas; $i++) {
            if ($i == 1 || $i == $total_paginas || ($i >= $pagina - $rango && $i <= $pagina + $rango)) {
                $active_class = ($pagina == $i) ? 'active' : '';
                $html_pagination .= "<li class='page-item {$active_class}'><a class='page-link rounded-2' href='#' onclick='cambiarPagina({$i}); return false;'>{$i}</a></li>";
            } elseif ($i == $pagina - $rango - 1 || $i == $pagina + $rango + 1) {
                $html_pagination .= '<li class="page-item disabled"><span class="page-link rounded-2">...</span></li>';
            }
        }
        $disabled_next = ($pagina >= $total_paginas) ? 'disabled' : '';
        $html_pagination .= "<li class='page-item {$disabled_next}'><a class='page-link rounded-2' href='#' onclick='cambiarPagina(".($pagina + 1)."); return false;'><i class='fa-solid fa-chevron-right fs-7'></i></a></li>";
        $html_pagination .= '</ul>';
    }

    header('Content-Type: application/json');
    echo json_encode([
        'rows' => $html_rows, 
        'pagination' => $html_pagination,
        'total_registros' => $total_registros,
        'sum_usd' => number_format($acumulado_usd_pagina, 2, ',', '.'),
        'sum_bs' => number_format($acumulado_bs_pagina, 2, ',', '.')
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Facturas | POS System</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --accent-blue: #3b82f6;
            --emerald: #10b981;
        }

        body { 
            background-color: var(--bg-main); 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: var(--text-main); 
            margin: 0; 
        }
        
        /* Sidebar Layout */
        .wrapper-layout { display: flex; min-height: 100vh; }
        .sidebar { 
            width: var(--sidebar-width); 
            background-color: #0f172a; 
            color: #f8fafc; 
            padding: 24px 18px; 
            flex-shrink: 0; 
            display: flex; 
            flex-direction: column; 
        }
        .sidebar h2 { 
            font-size: 16px; 
            font-weight: 700; 
            color: #f8fafc; 
            text-align: center; 
            margin-bottom: 30px; 
            letter-spacing: 1px; 
        }
        .sidebar nav { display: flex; flex-direction: column; gap: 6px; }
        .sidebar .nav-link { 
            display: flex; 
            align-items: center; 
            color: #94a3b8; 
            padding: 10px 14px; 
            border-radius: 10px; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 500; 
            transition: all 0.2s ease; 
        }
        .sidebar .nav-link:hover { background-color: #1e293b; color: #ffffff; }
        .sidebar .nav-link.active { background-color: var(--accent-blue); color: #ffffff; font-weight: 600; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .main-content { flex-grow: 1; overflow-x: hidden; padding-bottom: 40px; }

        /* UI Cards & Elements */
        .main-card { 
            border: none; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.03); 
            background: var(--card-bg); 
            padding: 28px; 
            margin-top: 24px; 
        }
        
        .kpi-card {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.04);
        }

        /* Typography & Utilities */
        .text-emerald { color: #059669; }
        .text-slate { color: #334155; }
        .fs-7 { font-size: 0.825rem; }

        /* Custom Table Styling */
        .table { --bs-table-bg: transparent; }
        .table th { 
            background-color: #f8fafc; 
            color: #475569; 
            font-weight: 600; 
            text-transform: uppercase; 
            font-size: 11px; 
            letter-spacing: 0.7px; 
            padding: 14px 16px; 
            border-bottom: 1px solid #e2e8f0; 
        }
        .table td { 
            padding: 16px; 
            vertical-align: middle; 
            font-size: 14px; 
            border-bottom: 1px solid #f1f5f9; 
        }
        .table-hover tbody tr:hover { background-color: #f8fafc; }

        /* Buttons & Controls */
        .hover-lift { transition: transform 0.15s ease, background-color 0.15s ease; }
        .hover-lift:hover { transform: translateY(-1px); background-color: #e2e8f0 !important; }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 10px 14px;
            font-size: 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        /* Pagination Styling */
        .pagination .page-link { color: #475569; border: 1px solid #e2e8f0; padding: 6px 12px; }
        .pagination .page-item.active .page-link { background-color: var(--accent-blue); border-color: var(--accent-blue); color: white; }
    </style>
</head>
<body>

<div class="wrapper-layout">
    <!-- Navigation Sidebar -->
    <aside class="sidebar">
        <h2>POS SYSTEM PRO</h2>
        <nav>
            <a href="ventas.php" class="nav-link">🛒 Ventas</a>
            <a href="inventario.php" class="nav-link">📦 Inventario</a>
            <a href="corte.php" class="nav-link">📊 Corte</a>
            <a href="estadisticas.php" class="nav-link">📈 Estadísticas</a>
            <a href="historial.php" class="nav-link">📋 Historial</a>
            <a href="historial_facturas.php" class="nav-link active">📋 Facturas</a>
            <a href="modulo_reporte_stock.php" class="nav-link">📦 Reporte Stock</a>
            <a href="creditos.php" class="nav-link">💳 Créditos</a>
            <a href="mercancia_departamentos.php" class="nav-link">📁 Departamentos</a>
            <a href="reporte_inventario.php" class="nav-link">📁 Rep. Inventario</a>
            <a href="configuracion.php" class="nav-link">⚙️ Ajustes</a>
            <a href="logout.php" class="nav-link" style="margin-top: 20px; color: #ef4444; font-weight: 600; border-top: 1px solid #334155; padding-top: 15px;">🚪 Salir</a>
        </nav>
    </aside>

    <!-- Main Workspace -->
    <main class="main-content">
        <div class="container-fluid px-4 px-lg-5 pt-4">
            
            <!-- Page Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-1"><i class="fa-solid fa-receipt text-primary me-2"></i>Historial de Facturas</h3>
                    <p class="text-muted small mb-0">Gestión global de ventas emitidas, consulta rápida y control de devoluciones.</p>
                </div>
                <div>
                    <a href="ventas.php" class="btn btn-white btn-sm border fw-semibold px-3 py-2 shadow-sm rounded-3">
                        <i class="fa-solid fa-arrow-left me-2 text-secondary"></i>Volver al Sistema
                    </a>
                </div>
            </div>

            <!-- Dashboard Summary Widgets -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="p-3 bg-primary-subtle text-primary rounded-4">
                            <i class="fa-solid fa-file-invoice fa-xl"></i>
                        </div>
                        <div>
                            <span class="text-muted fs-7 fw-medium d-block">Facturas Totales</span>
                            <h4 class="fw-bold text-dark mb-0" id="stat-total-facturas">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="p-3 bg-success-subtle text-success rounded-4">
                            <i class="fa-solid fa-dollar-sign fa-xl"></i>
                        </div>
                        <div>
                            <span class="text-muted fs-7 fw-medium d-block">Monto Pág. (USD)</span>
                            <h4 class="fw-bold text-dark mb-0">$ <span id="stat-sum-usd">0.00</span></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kpi-card d-flex align-items-center gap-3">
                        <div class="p-3 bg-info-subtle text-info rounded-4">
                            <i class="fa-solid fa-coins fa-xl"></i>
                        </div>
                        <div>
                            <span class="text-muted fs-7 fw-medium d-block">Monto Pág. (Bs.)</span>
                            <h4 class="fw-bold text-dark mb-0">Bs. <span id="stat-sum-bs">0,00</span></h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Table Section -->
            <div class="main-card">
                <!-- Filters Bar -->
                <div class="row g-3 mb-4 align-items-end">
                    <div class="col-md-5 col-sm-12">
                        <label class="form-label fw-semibold text-secondary fs-7">Buscar por Factura o Cliente</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0 rounded-start-3"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" id="buscar" class="form-control border-start-0" placeholder="Escribe N° de factura o nombre...">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-8">
                        <label class="form-label fw-semibold text-secondary fs-7">Filtrar por Método de Pago</label>
                        <select id="metodo" class="form-select">
                            <option value="">Todos los métodos</option>
                            <option value="Efectivo Bs.">Efectivo Bs.</option>
                            <option value="Punto">Punto</option>
                            <option value="Crédito">Crédito</option>
                            <option value="Cashea">Cashea</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-4 d-flex gap-2">
                        <button type="button" id="btn-limpiar" class="btn btn-light border w-100 fw-semibold text-secondary rounded-3 hover-lift">
                            <i class="fa-solid fa-eraser me-1"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th width="8%">Factura</th>
                                <th width="18%">Fecha / Hora</th>
                                <th width="20%">Cliente</th>
                                <th width="12%">Método</th>
                                <th width="12%">Tasa Hist.</th>
                                <th width="10%">Total USD</th>
                                <th width="10%">Total Bs.</th>
                                <th width="10%" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-contenido"></tbody>
                    </table>
                </div>

                <!-- Pagination footer -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <span class="text-muted fs-7">Mostrando registros guardados</span>
                    <nav id="tabla-paginacion"></nav>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Previsualización -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold text-dark fs-6">Previsualización Factura <span id="md-id" class="text-primary font-monospace"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="md-contenido"></div>
            <div class="modal-footer bg-light border-0 py-2">
                <button type="button" class="btn btn-sm btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Devoluciones -->
<div class="modal fade" id="modalDevolucion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-warning-subtle border-0 py-3">
                <h5 class="modal-title fw-bold text-dark fs-6"><i class="fa-solid fa-rotate-left me-2 text-warning"></i>Devolución por Ítem - Factura #<span id="md-dev-id"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Artículo</th>
                                <th class="text-center">Comprado</th>
                                <th class="text-end">Precio U.</th>
                                <th class="text-center" width="20%">Cant. a Devolver</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="md-dev-items"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let paginaActual = 1;
let modalDevolucionInstance = null;

document.addEventListener("DOMContentLoaded", function() {
    cargarTabla();
    modalDevolucionInstance = new bootstrap.Modal(document.getElementById('modalDevolucion'));

    document.getElementById('buscar').addEventListener('input', () => { paginaActual = 1; cargarTabla(); });
    document.getElementById('metodo').addEventListener('change', () => { paginaActual = 1; cargarTabla(); });
    
    document.getElementById('btn-limpiar').addEventListener('click', () => {
        document.getElementById('buscar').value = "";
        document.getElementById('metodo').value = "";
        paginaActual = 1;
        cargarTabla();
    });
});

function cargarTabla() {
    let buscar = encodeURIComponent(document.getElementById('buscar').value);
    let metodo = encodeURIComponent(document.getElementById('metodo').value);
    
    fetch(`historial_facturas.php?ajax=1&p=${paginaActual}&buscar=${buscar}&metodo=${metodo}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('tabla-contenido').innerHTML = data.rows;
            document.getElementById('tabla-paginacion').innerHTML = data.pagination;
            
            // Actualizar tarjetas de KPI
            document.getElementById('stat-total-facturas').innerText = data.total_registros;
            document.getElementById('stat-sum-usd').innerText = data.sum_usd;
            document.getElementById('stat-sum-bs').innerText = data.sum_bs;
        });
}

function cambiarPagina(p) {
    paginaActual = p;
    cargarTabla();
}

function verDetalleFactura(id) {
    document.getElementById('md-id').innerText = '#' + id;
    document.getElementById('md-contenido').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    new bootstrap.Modal(document.getElementById('modalDetalle')).show();

    fetch('obtener_detalle_factura.php?id=' + id)
        .then(res => res.text())
        .then(html => document.getElementById('md-contenido').innerHTML = html);
}

function abrirModalDevolucion(id) {
    document.getElementById('md-dev-id').innerText = id;
    modalDevolucionInstance.show();
    cargarItemsDevolucion(id);
}

function cargarItemsDevolucion(id) {
    document.getElementById('md-dev-items').innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-warning"></div></td></tr>';

    fetch(`procesar_devolucion.php?action=listar&id_venta=${id}`)
        .then(res => res.json())
        .then(items => {
            let html = "";
            if(items.length === 0){
                html = '<tr><td colspan="5" class="text-center text-muted py-4">No quedan artículos disponibles para devolver en esta factura.</td></tr>';
            } else {
                items.forEach(item => {
                    let precioBs = parseFloat(item.precio_bs || 0);
                    let precioUsd = parseFloat(item.precio_usd || 0);

                    html += `<tr>
                        <td><span class="fw-semibold text-dark">${item.descripcion}</span></td>
                        <td class="text-center fw-medium">${parseFloat(item.cantidad)}</td>
                        <td class="text-end">
                            <span class="fw-bold text-dark d-block">Bs. ${precioBs.toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                            <small class="text-muted" style="font-size: 11px;">($${precioUsd.toFixed(2)})</small>
                        </td>
                        <td>
                            <input type="number" id="cant_dev_${item.id}" class="form-control form-control-sm text-center font-monospace" value="${item.cantidad}" min="0.01" max="${item.cantidad}" step="any">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm rounded-3 shadow-sm px-3" onclick="procesarDevolucionItem(${item.id})"><i class="fa-solid fa-reply me-1"></i> Devolver</button>
                        </td>
                    </tr>`;
                });
            }
            document.getElementById('md-dev-items').innerHTML = html;
        });
}

function procesarDevolucionItem(idItem) {
    let cantDevolver = parseFloat(document.getElementById(`cant_dev_${idItem}`).value);
    if (isNaN(cantDevolver) || cantDevolver <= 0) {
        alert("Por favor introduce una cantidad válida.");
        return;
    }

    if (!confirm("¿Seguro que deseas procesar la devolución de esta cantidad? Se reintegrará al inventario y se descontará del corte.")) return;

    let formData = new FormData();
    formData.append('id_detalle', idItem);
    formData.append('cantidad_devolver', cantDevolver);

    fetch('procesar_devolucion.php?action=devolver', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(resp => {
            if (resp.trim() === "OK") {
                alert("¡Devolución procesada con éxito! Inventario y balances de caja actualizados.");
            } else {
                alert(resp);
            }
            let idVenta = document.getElementById('md-dev-id').innerText;
            
            cargarTabla(); 
            cargarItemsDevolucion(idVenta); 
        })
        .catch(err => console.error("Error:", err));
}

function reimprimirFactura(id) {
    var ancho = 400; var alto = 600;
    var x = (screen.width / 2) - (ancho / 2); var y = (screen.height / 2) - (alto / 2);
    window.open('imprimir_factura.php?id=' + id, 'Factura', `width=${ancho},height=${alto},left=${x},top=${y},scrollbars=yes`);
}
</script>
</body>
</html>