<?php
/**
 * Barra lateral compartida del POS.
 * La página activa se obtiene automáticamente desde el nombre del archivo.
 */
$current_page = basename($_SERVER['PHP_SELF']);

function pos_sidebar_active(string $current_page, array $pages): string {
    return in_array($current_page, $pages, true) ? ' active' : '';
}
?>
<style>
    .pos-sidebar {
        width: 280px;
        min-width: 280px;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
        box-sizing: border-box;
        background: #1e283c;
        padding: 40px 15px 20px;
        overflow-y: auto;
        box-shadow: 4px 0 15px rgba(0, 0, 0, 0.15);
    }

    .pos-sidebar h2 {
        color: #ffffff;
        font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
        font-size: 1.3rem;
        text-align: center;
        letter-spacing: 1.5px;
        margin: 0 0 40px;
        text-transform: uppercase;
        font-weight: 800;
    }

    .pos-sidebar nav {
        display: flex;
        flex-direction: column;
    }

    .pos-sidebar .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 20px;
        margin-bottom: 4px;
        border-radius: 12px;
        color: #94a3b8;
        text-decoration: none;
        font-family: 'Inter', sans-serif;
        font-size: 0.9rem;
        font-weight: 600;
        transition: 0.2s;
    }

    .pos-sidebar .nav-link:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #ffffff;
    }

    .pos-sidebar .nav-link.active {
        background: #3b82f6;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .pos-sidebar .nav-link.salir {
        margin-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0;
        color: #ff5252;
    }

    .pos-sidebar-spacer,
    .main-with-pos-sidebar {
        margin-left: 280px;
    }

    @media (max-width: 768px) {
        .pos-sidebar {
            position: relative;
            width: 100%;
            min-width: 100%;
            height: auto;
        }

        .pos-sidebar-spacer,
        .main-with-pos-sidebar {
            margin-left: 0;
        }

        body.pos-with-sidebar {
            display: block;
        }
    }
</style>

<aside class="pos-sidebar">
    <h2>POS SYSTEM PRO</h2>
    <nav>
        <a href="ventas.php" class="nav-link<?= pos_sidebar_active($current_page, ['ventas.php']) ?>">🛒 Ventas</a>
        <a href="inventario.php" class="nav-link<?= pos_sidebar_active($current_page, ['inventario.php']) ?>">📦 Inventario</a>
        <a href="corte.php" class="nav-link<?= pos_sidebar_active($current_page, ['corte.php']) ?>">📊 Corte</a>
        <a href="estadisticas.php" class="nav-link<?= pos_sidebar_active($current_page, ['estadisticas.php']) ?>">📊 Estadisticas</a>
        <a href="historial.php" class="nav-link<?= pos_sidebar_active($current_page, ['historial.php']) ?>">📋 HISTORIAL</a>
        <a href="historial_facturas.php" class="nav-link<?= pos_sidebar_active($current_page, ['historial_facturas.php', 'facturas.php']) ?>">📋 FACTURAS</a>
        <a href="proveedores.php" class="nav-link<?= pos_sidebar_active($current_page, ['proveedores.php']) ?>">🚚 Proveedores</a>
        <a href="modulo_reporte_stock.php" class="nav-link<?= pos_sidebar_active($current_page, ['modulo_reporte_stock.php', 'reporte_inventario.php']) ?>">📋 Reporte_stock</a>
        <a href="creditos.php" class="nav-link<?= pos_sidebar_active($current_page, ['creditos.php']) ?>">💳 Créditos</a>
        <a href="mercancia_departamentos.php" class="nav-link<?= pos_sidebar_active($current_page, ['mercancia_departamentos.php']) ?>">📁 Departamentos</a>
        <a href="configuracion.php" class="nav-link<?= pos_sidebar_active($current_page, ['configuracion.php']) ?>">⚙️ Ajustes</a>
        <a href="logout.php" class="nav-link salir">🚪 Salir</a>
    </nav>
</aside>
