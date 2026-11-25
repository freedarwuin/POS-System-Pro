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
