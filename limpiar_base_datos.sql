-- Limpia los datos operativos de la base puntodeventa.
-- Conserva las tablas y datos de: configuracion, metodos_pago, usuarios y licencia.
-- IMPORTANTE: DELETE elimina permanentemente los registros; AUTO_INCREMENT se reinicia al final.
-- Haz un respaldo antes de ejecutar este script.

USE `puntodeventa`;

-- MySQL no permite TRUNCATE sobre tablas referenciadas por claves foráneas,
-- incluso con FOREIGN_KEY_CHECKS desactivado. DELETE sí funciona en este caso.
SET FOREIGN_KEY_CHECKS = 0;

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

-- DELETE no reinicia AUTO_INCREMENT como TRUNCATE.
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

SET FOREIGN_KEY_CHECKS = 1;
