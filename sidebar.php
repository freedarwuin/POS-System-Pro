<?php
/**
 * Navegación lateral compartida del POS.
 * Mantiene un aspecto uniforme en todos los módulos que incluyen este archivo.
 */
$current_page = basename($_SERVER['PHP_SELF'] ?? '');

function pos_sidebar_link(string $current_page, array $pages, string $href, string $icon, string $label): void {
    $active = in_array($current_page, $pages, true);
    ?>
    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
       class="pos-sidebar__link<?= $active ? ' is-active' : '' ?>"
       <?= $active ? 'aria-current="page"' : '' ?>>
        <span class="pos-sidebar__icon" aria-hidden="true"><?= $icon ?></span>
        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
    </a>
    <?php
}
?>
<link rel="stylesheet" href="sidebar.css">

<button class="pos-sidebar__toggle" type="button" aria-label="Abrir menú de navegación"
        aria-controls="pos-sidebar" aria-expanded="false" id="pos-sidebar-toggle">
    <span aria-hidden="true">☰</span>
</button>
<div class="pos-sidebar__backdrop" id="pos-sidebar-backdrop" hidden></div>

<aside class="pos-sidebar" id="pos-sidebar" aria-label="Navegación principal">
    <h2 class="pos-sidebar__title">POS SYSTEM PRO</h2>
    <nav class="pos-sidebar__nav">
        <?php pos_sidebar_link($current_page, ['ventas.php'], 'ventas.php', '🛒', 'Ventas'); ?>
        <?php pos_sidebar_link($current_page, ['inventario.php'], 'inventario.php', '📦', 'Inventario'); ?>
        <?php pos_sidebar_link($current_page, ['corte.php'], 'corte.php', '📊', 'Corte'); ?>
        <?php pos_sidebar_link($current_page, ['estadisticas.php'], 'estadisticas.php', '📊', 'Estadísticas'); ?>
        <?php pos_sidebar_link($current_page, ['historial.php'], 'historial.php', '📋', 'HISTORIAL'); ?>
        <?php pos_sidebar_link($current_page, ['historial_facturas.php', 'facturas.php'], 'historial_facturas.php', '🧾', 'FACTURAS'); ?>
        <?php pos_sidebar_link($current_page, ['proveedores.php'], 'proveedores.php', '🚚', 'Proveedores'); ?>
        <?php pos_sidebar_link($current_page, ['modulo_reporte_stock.php', 'reporte_inventario.php'], 'modulo_reporte_stock.php', '📋', 'Reporte_stock'); ?>
        <?php pos_sidebar_link($current_page, ['creditos.php'], 'creditos.php', '💳', 'Créditos'); ?>
        <?php pos_sidebar_link($current_page, ['mercancia_departamentos.php'], 'mercancia_departamentos.php', '📁', 'Departamentos'); ?>
        <?php pos_sidebar_link($current_page, ['gestion_usuarios.php'], 'gestion_usuarios.php', '👥', 'Usuarios'); ?>
        <?php pos_sidebar_link($current_page, ['configuracion.php'], 'configuracion.php', '⚙️', 'Ajustes'); ?>
        <?php if (($_SESSION['rol'] ?? '') === 'Admin'): ?>
            <?php pos_sidebar_link($current_page, ['plan_respaldos.php'], 'plan_respaldos.php', '💾', 'Plan de respaldos'); ?>
        <?php endif; ?>
        <a href="logout.php" class="pos-sidebar__link pos-sidebar__logout">
            <span class="pos-sidebar__icon" aria-hidden="true">🚪</span><span>Salir</span>
        </a>
    </nav>
</aside>

<script>
(() => {
    const sidebar = document.getElementById('pos-sidebar');
    const toggle = document.getElementById('pos-sidebar-toggle');
    const close = document.getElementById('pos-sidebar-close');
    const backdrop = document.getElementById('pos-sidebar-backdrop');
    if (!sidebar || !toggle || !backdrop) return;

    const mobileQuery = window.matchMedia('(max-width: 900px)');
    let isOpen = !mobileQuery.matches;

    function setOpen(open) {
        isOpen = open;
        sidebar.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');
        backdrop.hidden = !open || !mobileQuery.matches;
        document.body.classList.toggle('pos-sidebar-layout', open && !mobileQuery.matches);
        document.body.classList.toggle('pos-sidebar-mobile-open', open && mobileQuery.matches);
    }

    toggle.addEventListener('click', () => setOpen(!isOpen));
    close?.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && isOpen && mobileQuery.matches) {
            setOpen(false);
            toggle.focus();
        }
    });
    mobileQuery.addEventListener('change', () => setOpen(!mobileQuery.matches));
    setOpen(isOpen);
})();
</script>
