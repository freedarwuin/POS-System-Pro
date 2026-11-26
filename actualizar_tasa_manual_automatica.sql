-- Migración idempotente para soporte de tasa manual y automática.
-- Puede ejecutarse varias veces y no requiere ADD COLUMN IF NOT EXISTS.

SET @db_actual = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_actual AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'tasa_automatica') = 0,
    'ALTER TABLE configuracion ADD COLUMN tasa_automatica TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE migracion FROM @sql;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_actual AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'tasa_manual') = 0,
    'ALTER TABLE configuracion ADD COLUMN tasa_manual DECIMAL(10,2) NULL',
    'SELECT 1'
);
PREPARE migracion FROM @sql;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_actual AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'tasa_dolar_api') = 0,
    'ALTER TABLE configuracion ADD COLUMN tasa_dolar_api DECIMAL(10,8) NULL',
    'SELECT 1'
);
PREPARE migracion FROM @sql;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_actual AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'tasa_fecha_actualizacion') = 0,
    'ALTER TABLE configuracion ADD COLUMN tasa_fecha_actualizacion DATETIME NULL',
    'SELECT 1'
);
PREPARE migracion FROM @sql;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_actual AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'tasa_fuente') = 0,
    'ALTER TABLE configuracion ADD COLUMN tasa_fuente VARCHAR(100) NULL',
    'SELECT 1'
);
PREPARE migracion FROM @sql;
EXECUTE migracion;
DEALLOCATE PREPARE migracion;

-- Inicializa la tasa manual solo cuando aún no tenga valor.
UPDATE configuracion
SET tasa_manual = tasa_dolar
WHERE id = 1 AND tasa_manual IS NULL;
