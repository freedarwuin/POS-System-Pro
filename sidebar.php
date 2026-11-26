<?php
/**
 * Barra lateral compartida del POS.
 * Resalta automáticamente la página activa y se adapta a pantallas pequeñas.
 */
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$sidebar_overlay = $current_page === 'ventas.php';
$sidebar_usuario = $_SESSION['usuario'] ?? 'Usuario';
$sidebar_rol = $_SESSION['rol'] ?? '';

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

<aside class="pos-sidebar<?= $sidebar_overlay ? ' pos-sidebar--overlay' : '' ?>" id="pos-sidebar"
       aria-label="Navegación principal" data-overlay="<?= $sidebar_overlay ? 'true' : 'false' ?>">
    <div class="pos-sidebar__brand">
        <a href="inventario.php" class="pos-sidebar__brand-link" aria-label="POS System Pro, inicio">
            <span class="pos-sidebar__brand-mark" aria-hidden="true">P</span>
            <span class="pos-sidebar__brand-copy">
                <strong>POS SYSTEM</strong>
                <small>PRO</small>
            </span>
        </a>
        <button class="pos-sidebar__close" type="button" aria-label="Cerrar menú" id="pos-sidebar-close">×</button>
    </div>

    <div class="pos-sidebar__user">
        <span class="pos-sidebar__avatar" aria-hidden="true">👤</span>
        <span class="pos-sidebar__user-copy">
            <strong><?= htmlspecialchars((string)$sidebar_usuario, ENT_QUOTES, 'UTF-8') ?></strong>
            <small><?= htmlspecialchars((string)$sidebar_rol, ENT_QUOTES, 'UTF-8') ?></small>
        </span>
    </div>

    <nav class="pos-sidebar__nav">
        <span class="pos-sidebar__section-label">OPERACIÓN</span>
        <?php pos_sidebar_link($current_page, ['ventas.php'], 'ventas.php', '🛒', 'Ventas'); ?>
        <?php pos_sidebar_link($current_page, ['inventario.php'], 'inventario.php', '📦', 'Inventario'); ?>
        <?php pos_sidebar_link($current_page, ['nueva_compra.php'], 'nueva_compra.php', '🧾', 'Nueva compra'); ?>
        <?php pos_sidebar_link($current_page, ['proveedores.php'], 'proveedores.php', '🚚', 'Proveedores'); ?>
        <?php pos_sidebar_link($current_page, ['mercancia_departamentos.php'], 'mercancia_departamentos.php', '🗂️', 'Departamentos'); ?>

        <span class="pos-sidebar__section-label">CAJA Y CLIENTES</span>
        <?php pos_sidebar_link($current_page, ['corte.php'], 'corte.php', '💵', 'Caja y corte'); ?>
        <?php pos_sidebar_link($current_page, ['historial.php'], 'historial.php', '📋', 'Historial'); ?>
        <?php pos_sidebar_link($current_page, ['historial_facturas.php', 'facturas.php'], 'historial_facturas.php', '🧾', 'Facturas'); ?>
        <?php pos_sidebar_link($current_page, ['creditos.php'], 'creditos.php', '💳', 'Créditos'); ?>

        <span class="pos-sidebar__section-label">ANÁLISIS Y SISTEMA</span>
        <?php pos_sidebar_link($current_page, ['estadisticas.php'], 'estadisticas.php', '📈', 'Estadísticas'); ?>
        <?php pos_sidebar_link($current_page, ['reporte_ventas.php'], 'reporte_ventas.php', '📊', 'Reporte de ventas'); ?>
        <?php pos_sidebar_link($current_page, ['modulo_reporte_stock.php', 'reporte_inventario.php'], 'modulo_reporte_stock.php', '📦', 'Reporte de stock'); ?>
        <?php pos_sidebar_link($current_page, ['gestion_usuarios.php'], 'gestion_usuarios.php', '👥', 'Usuarios'); ?>
        <?php pos_sidebar_link($current_page, ['configuracion.php'], 'configuracion.php', '⚙️', 'Configuración'); ?>
    </nav>

    <div class="pos-sidebar__footer">
        <a href="logout.php" class="pos-sidebar__logout">
            <span aria-hidden="true">↪</span> Cerrar sesión
        </a>
        <small>POS System Pro</small>
    </div>
</aside>

<script>
(() => {
    const sidebar = document.getElementById('pos-sidebar');
    const toggle = document.getElementById('pos-sidebar-toggle');
    const close = document.getElementById('pos-sidebar-close');
    const backdrop = document.getElementById('pos-sidebar-backdrop');
    if (!sidebar || !toggle || !backdrop) return;

    const mobileQuery = window.matchMedia('(max-width: 900px)');
    const overlayPage = sidebar.dataset.overlay === 'true';
    let isOpen = !mobileQuery.matches && !overlayPage;

    function setOpen(open) {
        isOpen = open;
        sidebar.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');
        backdrop.hidden = !open || !mobileQuery.matches;
        document.body.classList.toggle('pos-sidebar-layout', open && !mobileQuery.matches && !overlayPage);
        document.body.classList.toggle('pos-sidebar-mobile-open', open && mobileQuery.matches);
    }

    toggle.addEventListener('click', () => setOpen(!isOpen));
    close?.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && isOpen) {
            setOpen(false);
            toggle.focus();
        }
    });
    mobileQuery.addEventListener('change', () => setOpen(!mobileQuery.matches && !overlayPage));
    setOpen(isOpen);
})();
</script>
