-- Limpia los datos operativos de la base puntodeventa.
-- Conserva las tablas y datos de: configuracion, metodos_pago y usuarios.
-- IMPORTANTE: TRUNCATE elimina permanentemente los registros y reinicia AUTO_INCREMENT.
-- Haz un respaldo antes de ejecutar este script.

USE `puntodeventa`;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `abonos_proveedores`;
TRUNCATE TABLE `aperturas_caja`;
TRUNCATE TABLE `auditoria`;
TRUNCATE TABLE `cajas_apertura`;
TRUNCATE TABLE `clientes_credito`;
TRUNCATE TABLE `compras_proveedores`;
TRUNCATE TABLE `configuracion_pagomovil`;
TRUNCATE TABLE `departamentos`;
TRUNCATE TABLE `detalles_pagos`;
TRUNCATE TABLE `detalle_compras`;
TRUNCATE TABLE `detalle_ventas`;
TRUNCATE TABLE `facturas_estaticas_items`;
TRUNCATE TABLE `facturas_proveedores`;
TRUNCATE TABLE `gastos`;
TRUNCATE TABLE `historial_abonos`;
TRUNCATE TABLE `historial_movimientos`;
TRUNCATE TABLE `historial_pagomovil`;
TRUNCATE TABLE `historial_stock`;
TRUNCATE TABLE `licencia`;
TRUNCATE TABLE `pagos_credito`;
TRUNCATE TABLE `pagos_ventas`;
TRUNCATE TABLE `productos`;
TRUNCATE TABLE `proveedores`;
TRUNCATE TABLE `registro_pago_movil`;
TRUNCATE TABLE `turnos_caja`;
TRUNCATE TABLE `ventas`;
TRUNCATE TABLE `ventas_cashea`;
TRUNCATE TABLE `ventas_pagos`;

SET FOREIGN_KEY_CHECKS = 1;
