<?php
/**
 * Utilidades del plan de recordatorios de respaldos.
 * La creación idempotente permite actualizar instalaciones existentes al abrir el módulo.
 */
function posAsegurarTablaPlanRespaldos(mysqli $conexion): void
{
    $sql = "CREATE TABLE IF NOT EXISTS plan_respaldos (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        frecuencia ENUM('diario', 'semanal', 'mensual') NOT NULL DEFAULT 'semanal',
        ultimo_respaldo DATETIME DEFAULT NULL,
        actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!mysqli_query($conexion, $sql)) {
        throw new RuntimeException('No se pudo preparar el plan de respaldos: ' . mysqli_error($conexion));
    }

    if (!mysqli_query($conexion, "INSERT IGNORE INTO plan_respaldos (id, activo, frecuencia) VALUES (1, 1, 'semanal')")) {
        throw new RuntimeException('No se pudo inicializar el plan de respaldos: ' . mysqli_error($conexion));
    }
}

function posObtenerPlanRespaldos(mysqli $conexion): array
{
    posAsegurarTablaPlanRespaldos($conexion);
    $resultado = mysqli_query($conexion, 'SELECT id, activo, frecuencia, ultimo_respaldo FROM plan_respaldos WHERE id = 1 LIMIT 1');
    if (!$resultado) {
        throw new RuntimeException('No se pudo consultar el plan de respaldos: ' . mysqli_error($conexion));
    }

    $plan = mysqli_fetch_assoc($resultado);
    mysqli_free_result($resultado);

    return $plan ?: ['id' => 1, 'activo' => 1, 'frecuencia' => 'semanal', 'ultimo_respaldo' => null];
}

function posPlanRespaldoVencido(array $plan, ?DateTimeImmutable $ahora = null): bool
{
    if (empty($plan['activo'])) {
        return false;
    }
    if (empty($plan['ultimo_respaldo'])) {
        return true;
    }

    $intervalos = [
        'diario' => '+1 day',
        'semanal' => '+1 week',
        'mensual' => '+1 month',
    ];
    $frecuencia = (string) ($plan['frecuencia'] ?? 'semanal');
    $intervalo = $intervalos[$frecuencia] ?? $intervalos['semanal'];
    $ultimo = new DateTimeImmutable((string) $plan['ultimo_respaldo']);
    $proximo = $ultimo->modify($intervalo);

    return ($ahora ?? new DateTimeImmutable()) >= $proximo;
}
