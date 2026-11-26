-- Limpieza controlada de datos operativos de la base puntodeventa.
-- Conserva: configuracion, metodos_pago, usuarios y licencia.
-- ADVERTENCIA: DELETE elimina permanentemente los registros y no puede deshacerse.
-- Crear una copia de seguridad antes de ejecutar este script.

USE `puntodeventa`;

START TRANSACTION;

SET @guardar_claves_foraneas = @@SESSION.foreign_key_checks;
SET SESSION foreign_key_checks = 0;

DELETE FROM `abonos_proveedores`;
DELETE FROM `aperturas_caja`;
DELETE FROM `auditoria`;
DELETE FROM `cajas_apertura`;
DELETE FROM `clientes_credito`;
DELETE FROM `compras_proveedores`;
DELETE FROM `configuracion_pagomovil`;
DELETE FROM `departamentos`;
DELETE FROM `detalles_pagos`;
DELETE FROM `detalle_compras`;
DELETE FROM `detalle_ventas`;
DELETE FROM `facturas_estaticas_items`;
DELETE FROM `facturas_proveedores`;
DELETE FROM `gastos`;
DELETE FROM `historial_abonos`;
DELETE FROM `historial_movimientos`;
DELETE FROM `historial_pagomovil`;
DELETE FROM `historial_stock`;
DELETE FROM `pagos_credito`;
DELETE FROM `pagos_ventas`;
DELETE FROM `productos`;
DELETE FROM `proveedores`;
DELETE FROM `registro_pago_movil`;
DELETE FROM `turnos_caja`;
DELETE FROM `ventas`;
DELETE FROM `ventas_cashea`;
DELETE FROM `ventas_pagos`;

ALTER TABLE `abonos_proveedores` AUTO_INCREMENT = 1;
ALTER TABLE `aperturas_caja` AUTO_INCREMENT = 1;
ALTER TABLE `auditoria` AUTO_INCREMENT = 1;
ALTER TABLE `cajas_apertura` AUTO_INCREMENT = 1;
ALTER TABLE `clientes_credito` AUTO_INCREMENT = 1;
ALTER TABLE `compras_proveedores` AUTO_INCREMENT = 1;
ALTER TABLE `configuracion_pagomovil` AUTO_INCREMENT = 1;
ALTER TABLE `departamentos` AUTO_INCREMENT = 1;
ALTER TABLE `detalles_pagos` AUTO_INCREMENT = 1;
ALTER TABLE `detalle_compras` AUTO_INCREMENT = 1;
ALTER TABLE `detalle_ventas` AUTO_INCREMENT = 1;
ALTER TABLE `facturas_estaticas_items` AUTO_INCREMENT = 1;
ALTER TABLE `facturas_proveedores` AUTO_INCREMENT = 1;
ALTER TABLE `gastos` AUTO_INCREMENT = 1;
ALTER TABLE `historial_abonos` AUTO_INCREMENT = 1;
ALTER TABLE `historial_movimientos` AUTO_INCREMENT = 1;
ALTER TABLE `historial_pagomovil` AUTO_INCREMENT = 1;
ALTER TABLE `historial_stock` AUTO_INCREMENT = 1;
ALTER TABLE `pagos_credito` AUTO_INCREMENT = 1;
ALTER TABLE `pagos_ventas` AUTO_INCREMENT = 1;
ALTER TABLE `productos` AUTO_INCREMENT = 1;
ALTER TABLE `proveedores` AUTO_INCREMENT = 1;
ALTER TABLE `registro_pago_movil` AUTO_INCREMENT = 1;
ALTER TABLE `turnos_caja` AUTO_INCREMENT = 1;
ALTER TABLE `ventas` AUTO_INCREMENT = 1;
ALTER TABLE `ventas_cashea` AUTO_INCREMENT = 1;
ALTER TABLE `ventas_pagos` AUTO_INCREMENT = 1;

SET SESSION foreign_key_checks = @guardar_claves_foraneas;

COMMIT;

SELECT 'Limpieza completada. Las tablas protegidas no fueron modificadas.' AS resultado;
