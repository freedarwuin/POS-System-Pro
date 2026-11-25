-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 16-11-2026 a las 14:36:29
-- Versión del servidor: 8.0.17
-- Versión de PHP: 7.3.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `puntodeventa`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `abonos_proveedores`
--

CREATE TABLE `abonos_proveedores` (
  `id` int(11) NOT NULL,
  `id_factura` int(11) NOT NULL,
  `monto_abono_usd` decimal(15,2) NOT NULL DEFAULT '0.00',
  `monto_abono_bs` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tasa_cambio` decimal(15,2) NOT NULL DEFAULT '1.00',
  `metodo_pago` varchar(50) NOT NULL,
  `nota` text,
  `fecha_abono` datetime NOT NULL,
  `usuario` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aperturas_caja`
--

CREATE TABLE `aperturas_caja` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `monto_bs` double DEFAULT '0',
  `monto_usd` double DEFAULT '0',
  `fecha_apertura` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_cierre` datetime DEFAULT NULL,
  `estatus` enum('Abierta','Cerrada') DEFAULT 'Abierta'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `accion` varchar(255) NOT NULL,
  `detalles` text,
  `fecha_hora` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estructura de tabla para la tabla `cajas_apertura`
--

CREATE TABLE `cajas_apertura` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `monto_inicial_bs` decimal(10,2) DEFAULT NULL,
  `fecha_apertura` datetime DEFAULT CURRENT_TIMESTAMP,
  `estatus` enum('abierta','cerrada') DEFAULT 'abierta'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes_credito`
--

CREATE TABLE `clientes_credito` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `cedula_rif` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text,
  `limite_credito` decimal(10,2) DEFAULT '0.00',
  `deuda_actual` decimal(10,2) DEFAULT '0.00',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras_proveedores`
--

CREATE TABLE `compras_proveedores` (
  `id` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `nro_factura` varchar(50) NOT NULL,
  `total_compra` decimal(10,2) DEFAULT NULL,
  `fecha_compra` date DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'PROCESADA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int(1) NOT NULL,
  `nombre_negocio` varchar(100) NOT NULL,
  `rif` varchar(20) NOT NULL,
  `direccion` text,
  `mensaje_ticket` varchar(255) DEFAULT NULL,
  `tasa_dolar` decimal(10,2) NOT NULL,
  `moneda_simbolo` varchar(5) DEFAULT '$',
  `mensaje_factura` text,
  `puerto_impresora` varchar(100) DEFAULT NULL,
  `tipo_lector` varchar(50) DEFAULT 'teclado',
  `puerto_lector` varchar(50) DEFAULT NULL,
  `gaveta` varchar(50) DEFAULT 'impresora',
  `impuesto_iva` decimal(5,2) DEFAULT '16.00',
  `impuesto_igtf` decimal(5,2) DEFAULT '3.00',
  `usar_iva` tinyint(1) DEFAULT '1',
  `usar_igtf` tinyint(1) DEFAULT '0',
  `whatsapp_telefono` varchar(20) DEFAULT NULL,
  `whatsapp_api_key` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Estructura de tabla para la tabla `configuracion_pagomovil`
--

CREATE TABLE `configuracion_pagomovil` (
  `id` int(11) NOT NULL,
  `banco` varchar(100) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `cedula_rif` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `configuracion_pagomovil`
--

INSERT INTO `configuracion_pagomovil` (`id`, `banco`, `telefono`, `cedula_rif`) VALUES
(1, 'BANCO DE VENEZUELA (0102)', '0000000000', 'J-00000000');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departamentos`
--

CREATE TABLE `departamentos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalles_pagos`
--

CREATE TABLE `detalles_pagos` (
  `id` int(11) NOT NULL,
  `id_factura` int(11) NOT NULL,
  `metodo_pago` enum('Pago Móvil','Punto de Venta','Efectivo','Divisa') NOT NULL,
  `referencia` varchar(50) DEFAULT NULL,
  `monto_usd` decimal(10,2) DEFAULT NULL,
  `monto_bs` decimal(15,2) DEFAULT NULL,
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_compras`
--

CREATE TABLE `detalle_compras` (
  `id` int(11) NOT NULL,
  `id_compra` int(11) DEFAULT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `cantidad` decimal(10,3) DEFAULT NULL,
  `costo_unitario` decimal(10,2) DEFAULT NULL,
  `total_linea` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ventas`
--

CREATE TABLE `detalle_ventas` (
  `id` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` decimal(10,3) DEFAULT NULL,
  `precio` double NOT NULL,
  `descuento` decimal(5,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estructura de tabla para la tabla `facturas_estaticas_items`
--

CREATE TABLE `facturas_estaticas_items` (
  `id` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_usd` decimal(10,2) NOT NULL,
  `precio_bs` decimal(10,2) NOT NULL,
  `total_linea_bs` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estructura de tabla para la tabla `facturas_proveedores`
--

CREATE TABLE `facturas_proveedores` (
  `id` int(11) NOT NULL,
  `id_proveedor` int(11) NOT NULL,
  `numero_factura` varchar(50) NOT NULL,
  `descripcion_mercancia` text NOT NULL,
  `monto_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `saldo_pendiente` decimal(15,2) NOT NULL DEFAULT '0.00',
  `estado` enum('Pendiente','Abonada','Pagada') NOT NULL DEFAULT 'Pendiente',
  `archivo_factura` varchar(255) DEFAULT NULL,
  `fecha_factura` date NOT NULL,
  `fecha_registro` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estructura de tabla para la tabla `gastos`
--

CREATE TABLE `gastos` (
  `id` int(11) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `monto_usd` decimal(10,2) DEFAULT NULL,
  `monto_bs` decimal(10,2) DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_abonos`
--

CREATE TABLE `historial_abonos` (
  `id` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `monto_abonado` decimal(10,2) DEFAULT NULL,
  `fecha_pago` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_movimientos`
--

CREATE TABLE `historial_movimientos` (
  `id` int(11) NOT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `tipo_movimiento` varchar(50) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `detalle` text,
  `usuario` varchar(100) DEFAULT NULL,
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estructura de tabla para la tabla `historial_pagomovil`
--

CREATE TABLE `historial_pagomovil` (
  `id` int(11) NOT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `banco_emisor` varchar(100) NOT NULL,
  `referencia` varchar(50) NOT NULL,
  `monto_bs` decimal(10,2) NOT NULL,
  `monto_usd` decimal(10,2) NOT NULL,
  `fecha` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_stock`
--

CREATE TABLE `historial_stock` (
  `id` int(11) NOT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `descripcion_producto` varchar(255) DEFAULT NULL,
  `tipo_movimiento` enum('entrada','salida','ajuste') DEFAULT NULL,
  `cantidad_movida` int(11) DEFAULT NULL,
  `stock_anterior` int(11) DEFAULT NULL,
  `stock_nuevo` int(11) DEFAULT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `fecha_hora` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `licencia`
--

CREATE TABLE `licencia` (
  `id` int(11) NOT NULL DEFAULT '1',
  `codigo_licencia` varchar(255) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` enum('Activo','Expirado','Bloqueado') DEFAULT 'Activo',
  `servidor_id` varchar(255) DEFAULT NULL,
  `ultimo_uso` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `licencia`
--

INSERT INTO `licencia` (`id`, `codigo_licencia`, `fecha_vencimiento`, `estado`, `servidor_id`, `ultimo_uso`) VALUES
(1, 'POS-PRO-1370106E-2026', '2027-10-10', 'Activo', 'localhost', '2026-10-10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metodos_pago`
--

CREATE TABLE `metodos_pago` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `valor_option` varchar(50) NOT NULL,
  `icono` varchar(20) DEFAULT '',
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `metodos_pago`
--

INSERT INTO `metodos_pago` (`id`, `nombre`, `valor_option`, `icono`, `activo`) VALUES
(1, 'Pago Móvil', 'Pago Móvil', '📱', 1),
(2, 'Efectivo Bs.', 'Efectivo Bs.', '💸', 1),
(3, 'Dolares $', 'Efectivo $', '💵', 1),
(4, 'Punto de Venta', 'Punto', '💳', 1),
(5, 'Cashea', 'Cashea', '✨', 1),
(6, 'Pagos Múltiples', 'Multiple', '📑', 1),
(7, 'Crédito de Cliente', 'Crédito', '💳', 1),
(8, 'BioPago', 'BioPago', '👆', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_credito`
--

CREATE TABLE `pagos_credito` (
  `id` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_ventas`
--

CREATE TABLE `pagos_ventas` (
  `id` int(11) NOT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `monto_usd` decimal(10,2) DEFAULT NULL,
  `monto_bs` decimal(10,2) DEFAULT NULL,
  `tasa_pago` decimal(10,2) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `codigo_barras` varchar(50) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio_costo` decimal(10,2) DEFAULT NULL,
  `precio` double NOT NULL,
  `stock` decimal(10,3) DEFAULT '0.000',
  `tipo_venta` enum('unidad','granel','paquete') DEFAULT 'unidad',
  `unidad_medida` varchar(10) DEFAULT 'Unid',
  `id_departamento` int(11) DEFAULT NULL,
  `usa_inventario` tinyint(1) DEFAULT '1',
  `moneda` varchar(5) DEFAULT 'USD',
  `moneda_base` varchar(10) DEFAULT NULL,
  `precio_mayor` decimal(10,2) DEFAULT '0.00',
  `cantidad_mayor` int(11) DEFAULT '0',
  `precio_bulto` decimal(10,2) DEFAULT '0.00',
  `cantidad_bulto` int(11) DEFAULT '0',
  `id_proveedor` int(11) DEFAULT NULL,
  `usa_parametros_fiscales` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL,
  `nombre_empresa` varchar(150) NOT NULL,
  `rif_cedula` varchar(50) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` text,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registro_pago_movil`
--

CREATE TABLE `registro_pago_movil` (
  `id` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `banco_origen` varchar(100) NOT NULL,
  `referencia` varchar(50) NOT NULL,
  `monto_bs` decimal(12,2) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `fecha` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estructura de tabla para la tabla `turnos_caja`
--

CREATE TABLE `turnos_caja` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `fecha_apertura` datetime NOT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `fondo_usd` decimal(10,2) NOT NULL,
  `fondo_bs` decimal(10,2) NOT NULL,
  `estado` enum('Abierto','Cerrado') DEFAULT 'Abierto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `rol` enum('Admin','Cajero') DEFAULT 'Cajero',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `p_pos` tinyint(1) DEFAULT '1',
  `p_inventario` tinyint(1) DEFAULT '0',
  `p_facturas` tinyint(1) DEFAULT '0',
  `p_corte` tinyint(1) DEFAULT '0',
  `p_config` tinyint(1) DEFAULT '0',
  `permisos` text,
  `estado` varchar(20) DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id` int(11) NOT NULL,
  `cliente` varchar(255) NOT NULL DEFAULT 'Público General',
  `id_cliente` int(11) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `fecha` datetime NOT NULL,
  `estado` enum('PAGADA','ANULADA') NOT NULL DEFAULT 'PAGADA',
  `metodo_pago` varchar(50) DEFAULT 'Efectivo',
  `cashea_recargo` decimal(10,2) DEFAULT '0.00',
  `cashea_inicial` decimal(10,2) DEFAULT '0.00',
  `cashea_financiado_usd` decimal(10,2) DEFAULT '0.00',
  `cashea_metodo_inicial` varchar(50) DEFAULT NULL,
  `pago_efectivo_usd` decimal(10,2) DEFAULT '0.00',
  `usuario` varchar(100) DEFAULT NULL,
  `tasa_momento` decimal(10,2) DEFAULT NULL,
  `pagada` tinyint(1) NOT NULL DEFAULT '0',
  `pago_punto_bs` decimal(10,2) DEFAULT '0.00',
  `pago_efectivo_bs` decimal(10,2) DEFAULT '0.00',
  `pago_movil_bs` decimal(10,2) DEFAULT '0.00',
  `pago_biopago_bs` decimal(10,2) DEFAULT '0.00',
  `cambio_bs` decimal(10,2) DEFAULT '0.00',
  `cambio_usd` decimal(10,2) DEFAULT '0.00',
  `cashea_recargo_usd` decimal(10,2) DEFAULT '0.00',
  `cashea_inicial_usd` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estructura de tabla para la tabla `ventas_cashea`
--

CREATE TABLE `ventas_cashea` (
  `id` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `monto_recargo` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monto_inicial` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monto_financiado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fecha_registro` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas_pagos`
--

CREATE TABLE `ventas_pagos` (
  `id` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `metodo_pago` varchar(50) NOT NULL,
  `monto_usd` decimal(10,2) DEFAULT '0.00',
  `monto_bs` decimal(10,2) DEFAULT '0.00',
  `vuelto_usd` decimal(10,2) DEFAULT '0.00',
  `vuelto_bs` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `abonos_proveedores`
--
ALTER TABLE `abonos_proveedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_factura` (`id_factura`);

--
-- Indices de la tabla `aperturas_caja`
--
ALTER TABLE `aperturas_caja`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cajas_apertura`
--
ALTER TABLE `cajas_apertura`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `clientes_credito`
--
ALTER TABLE `clientes_credito`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula_rif` (`cedula_rif`);

--
-- Indices de la tabla `compras_proveedores`
--
ALTER TABLE `compras_proveedores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `configuracion_pagomovil`
--
ALTER TABLE `configuracion_pagomovil`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `detalles_pagos`
--
ALTER TABLE `detalles_pagos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `detalle_compras`
--
ALTER TABLE `detalle_compras`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_venta` (`id_venta`);

--
-- Indices de la tabla `facturas_estaticas_items`
--
ALTER TABLE `facturas_estaticas_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_venta` (`id_venta`);

--
-- Indices de la tabla `facturas_proveedores`
--
ALTER TABLE `facturas_proveedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_proveedor` (`id_proveedor`);

--
-- Indices de la tabla `gastos`
--
ALTER TABLE `gastos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `historial_abonos`
--
ALTER TABLE `historial_abonos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Indices de la tabla `historial_movimientos`
--
ALTER TABLE `historial_movimientos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `historial_pagomovil`
--
ALTER TABLE `historial_pagomovil`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `historial_stock`
--
ALTER TABLE `historial_stock`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `licencia`
--
ALTER TABLE `licencia`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pagos_credito`
--
ALTER TABLE `pagos_credito`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pagos_ventas`
--
ALTER TABLE `pagos_ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_venta` (`id_venta`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_barras` (`codigo_barras`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `registro_pago_movil`
--
ALTER TABLE `registro_pago_movil`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `turnos_caja`
--
ALTER TABLE `turnos_caja`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `ventas_cashea`
--
ALTER TABLE `ventas_cashea`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_venta` (`id_venta`);

--
-- Indices de la tabla `ventas_pagos`
--
ALTER TABLE `ventas_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_venta` (`id_venta`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `abonos_proveedores`
--
ALTER TABLE `abonos_proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `aperturas_caja`
--
ALTER TABLE `aperturas_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `cajas_apertura`
--
ALTER TABLE `cajas_apertura`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clientes_credito`
--
ALTER TABLE `clientes_credito`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `compras_proveedores`
--
ALTER TABLE `compras_proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuracion_pagomovil`
--
ALTER TABLE `configuracion_pagomovil`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `detalles_pagos`
--
ALTER TABLE `detalles_pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_compras`
--
ALTER TABLE `detalle_compras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT de la tabla `facturas_estaticas_items`
--
ALTER TABLE `facturas_estaticas_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de la tabla `facturas_proveedores`
--
ALTER TABLE `facturas_proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `gastos`
--
ALTER TABLE `gastos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_abonos`
--
ALTER TABLE `historial_abonos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_movimientos`
--
ALTER TABLE `historial_movimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT de la tabla `historial_pagomovil`
--
ALTER TABLE `historial_pagomovil`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_stock`
--
ALTER TABLE `historial_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `pagos_credito`
--
ALTER TABLE `pagos_credito`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos_ventas`
--
ALTER TABLE `pagos_ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `registro_pago_movil`
--
ALTER TABLE `registro_pago_movil`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `turnos_caja`
--
ALTER TABLE `turnos_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT de la tabla `ventas_cashea`
--
ALTER TABLE `ventas_cashea`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `ventas_pagos`
--
ALTER TABLE `ventas_pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `abonos_proveedores`
--
ALTER TABLE `abonos_proveedores`
  ADD CONSTRAINT `abonos_proveedores_ibfk_1` FOREIGN KEY (`id_factura`) REFERENCES `facturas_proveedores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD CONSTRAINT `fk_detalle_factura` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `facturas_proveedores`
--
ALTER TABLE `facturas_proveedores`
  ADD CONSTRAINT `facturas_proveedores_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `historial_abonos`
--
ALTER TABLE `historial_abonos`
  ADD CONSTRAINT `historial_abonos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes_credito` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pagos_ventas`
--
ALTER TABLE `pagos_ventas`
  ADD CONSTRAINT `pagos_ventas_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`);

--
-- Filtros para la tabla `ventas_cashea`
--
ALTER TABLE `ventas_cashea`
  ADD CONSTRAINT `ventas_cashea_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ventas_pagos`
--
ALTER TABLE `ventas_pagos`
  ADD CONSTRAINT `ventas_pagos_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
