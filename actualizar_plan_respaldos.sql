-- Esquema del plan de recordatorios de respaldos.
-- Idempotente: puede ejecutarse durante instalación o actualización sin perder datos.
CREATE TABLE IF NOT EXISTS `plan_respaldos` (
  `id` TINYINT UNSIGNED NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `frecuencia` ENUM('diario', 'semanal', 'mensual') NOT NULL DEFAULT 'semanal',
  `ultimo_respaldo` DATETIME DEFAULT NULL,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `plan_respaldos` (`id`, `activo`, `frecuencia`) VALUES (1, 1, 'semanal');
