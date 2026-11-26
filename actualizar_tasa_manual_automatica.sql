-- Migración para soporte de tasa manual y automática BCV.
-- Ejecutar una sola vez durante la actualización del sistema.
-- Compatible con versiones de MySQL que no admiten ADD COLUMN IF NOT EXISTS.

ALTER TABLE configuracion
    ADD COLUMN tasa_automatica TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN tasa_manual DECIMAL(10,2) NULL,
    ADD COLUMN tasa_dolar_api DECIMAL(10,8) NULL,
    ADD COLUMN tasa_fecha_actualizacion DATETIME NULL,
    ADD COLUMN tasa_fuente VARCHAR(100) NULL;

-- Inicializa la preferencia manual con la tasa operativa existente.
UPDATE configuracion
SET tasa_manual = tasa_dolar
WHERE id = 1 AND tasa_manual IS NULL;
