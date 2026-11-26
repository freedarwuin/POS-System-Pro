<?php
/**
 * Panel de recordatorio incluido únicamente en configuracion.php.
 * sidebar.php lo carga con la conexión y el plan ya preparados.
 */
if (empty($plan_respaldo_panel) || !is_array($plan_respaldo_panel)) {
    return;
}

$planPanelFrecuencias = ['diario' => 'Diario', 'semanal' => 'Semanal', 'mensual' => 'Mensual'];
$planPanelFrecuencia = (string) ($plan_respaldo_panel['frecuencia'] ?? 'semanal');
$planPanelUltimo = !empty($plan_respaldo_panel['ultimo_respaldo'])
    ? date('d/m/Y H:i', strtotime((string) $plan_respaldo_panel['ultimo_respaldo']))
    : 'Nunca se ha descargado un respaldo';
$planPanelVencido = posPlanRespaldoVencido($plan_respaldo_panel);
$planPanelProximo = null;
if (!empty($plan_respaldo_panel['ultimo_respaldo'])) {
    $planPanelIntervalos = ['diario' => '+1 day', 'semanal' => '+1 week', 'mensual' => '+1 month'];
    $planPanelProximo = (new DateTimeImmutable((string) $plan_respaldo_panel['ultimo_respaldo']))
        ->modify($planPanelIntervalos[$planPanelFrecuencia] ?? '+1 week');
}
$planPanelMensaje = (string) ($_SESSION['mensaje_plan_respaldo'] ?? '');
unset($_SESSION['mensaje_plan_respaldo']);
$planPanelEscapar = static function (string $valor): string {
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
};
?>
<template id="plan-respaldo-panel-template">
    <section id="plan-respaldos" style="margin-top:24px;padding-top:20px;border-top:1px solid #f1f1f1;">
        <h3 style="margin:0 0 8px;font-size:1rem;color:#3a2d32;">Plan de recordatorios</h3>
        <p style="font-size:.82rem;line-height:1.55;color:#666;margin:0 0 14px;">Elige cada cuánto recordar la descarga. El respaldo es manual; el sistema no crea ni envía archivos automáticamente.</p>
        <?php if ($planPanelMensaje !== ''): ?>
            <div role="status" style="margin-bottom:12px;padding:10px 12px;border-radius:10px;background:#ecfdf5;color:#065f46;font-size:.82rem;">
                <?= $planPanelEscapar($planPanelMensaje) ?>
            </div>
        <?php endif; ?>
        <?php if (empty($plan_respaldo_panel['activo'])): ?>
            <div style="margin-bottom:12px;padding:10px 12px;border-radius:10px;background:#f1f5f9;color:#334155;font-size:.82rem;">Los recordatorios están desactivados.</div>
        <?php elseif ($planPanelVencido): ?>
            <div role="alert" style="margin-bottom:12px;padding:10px 12px;border-radius:10px;background:#fef2f2;color:#7f1d1d;font-size:.82rem;"><strong>Respaldo pendiente.</strong> Ya corresponde realizar el respaldo <?= $planPanelEscapar($planPanelFrecuencias[$planPanelFrecuencia] ?? 'semanal') ?>.</div>
        <?php else: ?>
            <div style="margin-bottom:12px;padding:10px 12px;border-radius:10px;background:#ecfdf5;color:#065f46;font-size:.82rem;">Plan al día. Próximo recordatorio: <?= $planPanelEscapar($planPanelProximo->format('d/m/Y H:i')) ?>.</div>
        <?php endif; ?>
        <div style="font-size:.82rem;color:#475569;margin-bottom:14px;"><strong>Último respaldo registrado:</strong> <?= $planPanelEscapar($planPanelUltimo) ?></div>
        <form action="guardar_plan_respaldo.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $planPanelEscapar((string) $_SESSION['csrf_plan_respaldo']) ?>">
            <div style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
                <div style="flex:1;min-width:170px;">
                    <label for="frecuencia-plan-respaldo" style="font-size:.72rem;font-weight:800;color:#888;display:block;margin-bottom:6px;">FRECUENCIA</label>
                    <select id="frecuencia-plan-respaldo" name="frecuencia" required style="width:100%;background:#eef4fe;border:1px solid #dbe4f0;border-radius:10px;padding:11px;color:#222;font-size:.88rem;">
                        <?php foreach ($planPanelFrecuencias as $valor => $etiqueta): ?>
                            <option value="<?= $planPanelEscapar($valor) ?>" <?= $planPanelFrecuencia === $valor ? 'selected' : '' ?>><?= $planPanelEscapar($etiqueta) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label style="display:flex;align-items:center;gap:8px;font-size:.82rem;color:#475569;">
                    <input type="checkbox" name="activo" value="1" <?= !empty($plan_respaldo_panel['activo']) ? 'checked' : '' ?> style="width:18px;height:18px;">
                    Activar recordatorio
                </label>
                <button type="submit" style="border:0;border-radius:10px;background:#2563eb;color:#fff;padding:12px 16px;font-weight:700;cursor:pointer;">Guardar plan</button>
            </div>
        </form>
        <div style="margin-top:12px;font-size:.76rem;color:#64748b;">Haz clic en «Descargar Respaldo SQL» de arriba para generar la copia. La fecha se registra al generarla.</div>
    </section>
</template>
<script>
(() => {
    function insertarPanelPlanRespaldo() {
        const template = document.getElementById('plan-respaldo-panel-template');
        if (!template) return;
        const titulo = Array.from(document.querySelectorAll('.card-title'))
            .find(elemento => elemento.textContent.includes('Respaldo Estructurado'));
        const tarjeta = titulo?.closest('.glass-card');
        if (!tarjeta || tarjeta.querySelector('#plan-respaldos')) return;
        tarjeta.appendChild(template.content.cloneNode(true));
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', insertarPanelPlanRespaldo, { once: true });
    } else {
        insertarPanelPlanRespaldo();
    }
})();
</script>
