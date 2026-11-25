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

--
-- Volcado de datos para la tabla `abonos_proveedores`
--

INSERT INTO `abonos_proveedores` (`id`, `id_factura`, `monto_abono_usd`, `monto_abono_bs`, `tasa_cambio`, `metodo_pago`, `nota`, `fecha_abono`, `usuario`) VALUES
(1, 5, '12.00', '0.00', '655.00', 'Efectivo USD', 'pago completo', '2026-07-10 17:50:02', 'root');

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

--
-- Volcado de datos para la tabla `aperturas_caja`
--

INSERT INTO `aperturas_caja` (`id`, `id_usuario`, `monto_bs`, `monto_usd`, `fecha_apertura`, `fecha_cierre`, `estatus`) VALUES
(1, 15, 1000, 10, '2026-07-04 17:52:15', NULL, 'Cerrada'),
(2, 15, 2000, 10, '2026-07-04 17:52:59', NULL, 'Cerrada'),
(3, 15, 1000, 10, '2026-07-04 18:15:27', NULL, 'Cerrada'),
(4, 15, 1000, 10, '2026-07-04 18:19:35', NULL, 'Cerrada'),
(5, 15, 1000, 10, '2026-07-12 10:52:18', '2026-07-12 10:54:25', 'Cerrada');

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
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id`, `usuario`, `accion`, `detalles`, `fecha_hora`) VALUES
(1, 'root', 'CAMBIO PARAMETROS FINANCIEROS', 'Tasa cambiada de 655.00 a 655. IVA nuevo: 16% (Activo: 0). IGTF: 3% (Activo: 0).', '2026-07-04 12:34:56'),
(2, 'root', 'ACTUALIZACIÓN WHATSAPP', 'Se reconfiguró el número de WhatsApp receptor (+584142231178) para el envío automático de cortes de caja.', '2026-07-04 12:47:33'),
(3, 'root', 'CAMBIO PARAMETROS FINANCIEROS', 'Tasa cambiada de 655.00 a 744. IVA nuevo: 16% (Activo: 0). IGTF: 3% (Activo: 0).', '2026-07-24 10:15:46'),
(4, 'root', 'CAMBIO PARAMETROS FINANCIEROS', 'Tasa cambiada de 787.00 a 787.33. IVA nuevo: 16% (Activo: 0). IGTF: 3% (Activo: 0).', '2026-08-13 22:54:32'),
(5, 'root', 'CAMBIO PARAMETROS FINANCIEROS', 'Tasa cambiada de 787.33 a 876.36. IVA nuevo: 16% (Activo: 0). IGTF: 3% (Activo: 0).', '2026-10-11 09:43:21'),
(6, 'root', 'CAMBIO PARAMETROS FINANCIEROS', 'Tasa cambiada de 876.36 a 876.36. IVA nuevo: 16% (Activo: 1). IGTF: 3% (Activo: 0).', '2026-11-15 16:43:20');

-- --------------------------------------------------------

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

--
-- Volcado de datos para la tabla `clientes_credito`
--

INSERT INTO `clientes_credito` (`id`, `nombre`, `cedula_rif`, `telefono`, `direccion`, `limite_credito`, `deuda_actual`, `fecha_registro`) VALUES
(1, 'CHRISTIAN', '27283989', '04142231178', NULL, '100.00', '1.66', '2026-07-29 01:02:35');

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
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id`, `nombre_negocio`, `rif`, `direccion`, `mensaje_ticket`, `tasa_dolar`, `moneda_simbolo`, `mensaje_factura`, `puerto_impresora`, `tipo_lector`, `puerto_lector`, `gaveta`, `impuesto_iva`, `impuesto_igtf`, `usar_iva`, `usar_igtf`, `whatsapp_telefono`, `whatsapp_api_key`) VALUES
(1, 'Nuevo comercio DIstrubuidora', 'J-12345678', 'AV. ALI PRIMERA SANTA TERESA DEL TUY \r\nESTADO MIRANDA ', '*** Gracias por su compra ***', '876.36', '$', '', NULL, 'teclado', NULL, 'impresora', '16.00', '3.00', 1, 0, '+584142231178', '57888557');

-- --------------------------------------------------------

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
(1, 'BANCO DE VENEZUELA (0102)', '04121234567', 'J-123456789');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departamentos`
--

CREATE TABLE `departamentos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `departamentos`
--

INSERT INTO `departamentos` (`id`, `nombre`) VALUES
(1, 'CHARCUTERIAS'),
(2, 'VIVERES'),
(3, 'BEBIDAS');

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
-- Volcado de datos para la tabla `detalle_ventas`
--

INSERT INTO `detalle_ventas` (`id`, `id_venta`, `id_producto`, `cantidad`, `precio`, `descuento`) VALUES
(44, 43, 27, '0.099', 7.94, '0.00'),
(45, 44, 27, '0.099', 7.94, '0.00'),
(46, 45, 27, '0.099', 7.94, '0.00'),
(47, 46, 27, '0.099', 7.94, '0.00'),
(48, 47, 27, '-0.001', 7.94, '0.00'),
(49, 48, 27, '-0.001', 7.94, '0.00'),
(50, 49, 27, '0.099', 7.94, '0.00'),
(51, 50, 27, '0.099', 7.94, '0.00'),
(52, 51, 27, '0.099', 7.94, '0.00'),
(53, 52, 27, '0.099', 7.94, '0.00'),
(54, 53, 39, '1.000', 2.16, '0.00'),
(55, 54, 34, '2.000', 0.69, '0.00'),
(56, 54, 29, '1.000', 2.76, '0.00'),
(57, 54, 27, '5.000', 7.94, '0.00'),
(58, 55, 32, '1.000', 3.45, '0.00'),
(59, 55, 42, '1.000', 2.16, '0.00'),
(60, 55, 39, '1.000', 2.16, '0.00'),
(61, 56, 43, '1.000', 0.75, '0.00'),
(62, 56, 42, '1.000', 2.16, '0.00'),
(63, 56, 41, '1.000', 1.95, '0.00'),
(64, 57, 40, '1.000', 2.07, '0.00'),
(65, 57, 39, '1.000', 2.16, '0.00');

-- --------------------------------------------------------

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
-- Volcado de datos para la tabla `facturas_estaticas_items`
--

INSERT INTO `facturas_estaticas_items` (`id`, `id_venta`, `id_producto`, `descripcion`, `cantidad`, `precio_usd`, `precio_bs`, `total_linea_bs`) VALUES
(1, 47, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(2, 46, 27, 'Queso', '0.10', '7.94', '6251.40', '618.89'),
(3, 45, 27, 'Queso', '0.10', '7.94', '6251.40', '618.89'),
(4, 44, 27, 'Queso', '0.10', '7.94', '6251.40', '618.89'),
(5, 43, 27, 'Queso', '0.10', '7.94', '6251.40', '618.89'),
(6, 48, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(14, 48, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(15, 47, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(16, 48, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(17, 47, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(18, 49, 27, 'Queso', '0.10', '7.94', '6251.40', '618.89'),
(19, 48, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(20, 47, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(21, 50, 27, 'Queso', '0.10', '7.94', '6251.40', '618.89'),
(22, 48, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(23, 47, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(24, 51, 27, 'Queso', '0.10', '7.94', '6251.40', '618.89'),
(25, 48, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(26, 47, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(27, 53, 39, 'HARINA DE TRIGO', '1.00', '2.16', '1700.63', '1700.63'),
(28, 52, 27, 'Queso', '0.10', '7.94', '6251.40', '618.89'),
(29, 48, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(30, 47, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(31, 55, 32, 'Caramelos', '1.00', '3.45', '3023.44', '3023.44'),
(32, 55, 42, 'crema dental', '1.00', '2.16', '1892.94', '1892.94'),
(33, 55, 39, 'HARINA DE TRIGO', '1.00', '2.16', '1892.94', '1892.94'),
(34, 54, 34, 'hojas blancas', '2.00', '0.69', '604.69', '1209.38'),
(35, 54, 29, 'cesta', '1.00', '2.76', '2418.75', '2418.75'),
(36, 54, 27, 'Queso', '5.00', '7.94', '6958.30', '34791.50'),
(37, 48, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25'),
(38, 47, 27, 'Queso', '0.00', '7.94', '6251.40', '-6.25');

-- --------------------------------------------------------

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
-- Volcado de datos para la tabla `facturas_proveedores`
--

INSERT INTO `facturas_proveedores` (`id`, `id_proveedor`, `numero_factura`, `descripcion_mercancia`, `monto_total`, `saldo_pendiente`, `estado`, `archivo_factura`, `fecha_factura`, `fecha_registro`) VALUES
(5, 2, '123658', 'Artículos recibidos:\n- 57588557 - Harina Pan (Cant: 10)\n', '12.00', '0.00', 'Pagada', 'fact_1783705760_6a5130a003fb6.png', '2026-07-10', '2026-07-10 17:49:20'),
(6, 3, '541515151', 'Artículos recibidos:\n- 47458821 - Queso (Cant: 5)\n', '15.00', '15.00', 'Pendiente', NULL, '2026-07-11', '2026-07-11 15:50:32');

-- --------------------------------------------------------

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
-- Volcado de datos para la tabla `historial_movimientos`
--

INSERT INTO `historial_movimientos` (`id`, `id_producto`, `tipo_movimiento`, `cantidad`, `detalle`, `usuario`, `fecha`) VALUES
(1, 1, 'ENTRADA', 20, 'Registro Inicial (Unid)', 'root', '2026-07-02 16:31:02'),
(2, 1, 'VENTA', 3, 'Venta Factura #1 - Producto: producto ejemplo (Con Recargo Cashea)', 'root', '2026-07-02 16:32:09'),
(3, 2, 'ENTRADA', 20, 'Registro Inicial (Unid)', 'root', '2026-07-02 16:32:55'),
(4, 2, 'VENTA', 1, 'Venta Factura #2 - Producto: Caucho Delantero Rin 18 (Con Recargo Cashea)', 'root', '2026-07-02 16:34:27'),
(5, 2, 'VENTA', 1, 'Venta Factura #3 - Producto: Caucho Delantero Rin 18 (Con Recargo Cashea)', 'root', '2026-07-02 16:55:42'),
(6, 2, 'VENTA', 1, 'Venta Factura #4 - Producto: Caucho Delantero Rin 18 (Con Recargo Cashea)', 'root', '2026-07-02 16:57:08'),
(7, 2, 'VENTA', 1, 'Venta Factura #5 - Producto: Caucho Delantero Rin 18 (Con Recargo Cashea)', 'root', '2026-07-02 17:06:54'),
(8, 2, 'VENTA', 1, 'Venta Factura #6 - Producto: Caucho Delantero Rin 18 (Con Recargo Cashea)', 'root', '2026-07-03 19:03:31'),
(9, 2, 'VENTA', 1, 'Venta Factura #7 - Producto: Caucho Delantero Rin 18 (Con Recargo Cashea)', 'root', '2026-07-03 19:16:25'),
(10, 1, 'VENTA', 1, 'Venta Factura #8 - Producto: producto ejemplo', 'root', '2026-07-03 19:17:09'),
(11, 2, 'VENTA', 1, 'Venta Factura #9 - Producto: Caucho Delantero Rin 18 (Con Recargo Cashea)', 'root', '2026-07-03 19:19:11'),
(12, 2, 'VENTA', 1, 'Venta Factura #10 - Producto: Caucho Delantero Rin 18 (Con Recargo Cashea)', 'root', '2026-07-03 19:19:35'),
(13, 2, 'VENTA', 1, 'Venta Factura #11 - Producto: Caucho Delantero Rin 18 (Con Recargo Cashea)', 'root', '2026-07-03 19:30:44'),
(14, 2, 'VENTA', 1, 'Venta Factura #12 - Producto: Caucho Delantero Rin 18 (Con Recargo Cashea)', 'root', '2026-07-03 19:35:00'),
(15, 1, 'VENTA', 1, 'Venta Factura #13 - Producto: producto ejemplo', 'root', '2026-07-03 19:38:36'),
(16, 3, 'ENTRADA', 60, 'Registro Inicial (G)', 'root', '2026-07-03 19:39:39'),
(17, 3, 'VENTA', 0, 'Venta Factura #14 - Producto: QUESO', 'root', '2026-07-03 19:40:13'),
(18, 1, 'VENTA', 1, 'Venta Factura #15 - Producto: producto ejemplo', 'root', '2026-07-04 16:15:13'),
(19, 4, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-07-04 16:20:46'),
(20, 1, 'VENTA', 1, 'Venta Factura #16 - Producto: producto ejemplo', 'cajero', '2026-07-04 22:17:34'),
(21, 5, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-07-10 17:41:56'),
(22, 1, 'ELIMINACIÓN', 0, 'ELIMINADO: producto ejemplo', 'root', '2026-07-11 14:58:45'),
(23, 2, 'ELIMINACIÓN', 0, 'ELIMINADO: Caucho Delantero Rin 18', 'root', '2026-07-11 14:58:49'),
(24, 3, 'ELIMINACIÓN', 0, 'ELIMINADO: QUESO', 'root', '2026-07-11 14:58:52'),
(25, 4, 'ELIMINACIÓN', 0, 'ELIMINADO: Galleta', 'root', '2026-07-11 14:58:57'),
(26, 5, 'ELIMINACIÓN', 0, 'ELIMINADO: Harina Pan', 'root', '2026-07-11 14:59:00'),
(27, 26, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-07-11 15:40:41'),
(28, 27, 'ENTRADA', 30, 'Registro Inicial (KG)', 'root', '2026-07-11 15:49:01'),
(29, 27, 'VENTA', 40, 'Venta Factura #17 - Producto: Queso', 'root', '2026-07-11 15:51:33'),
(30, 27, 'AJUSTE (+)', 20, 'Ajuste manual de stock (Diferencia: 20)', 'root', '2026-07-11 15:59:59'),
(31, 26, 'AJUSTE PRECIO', 0, 'Venta: de 1.8 a 20', 'root', '2026-07-16 20:32:37'),
(32, 26, 'VENTA', 1, 'Venta Factura #18 - Producto: Harina Pan (Cashea)', 'root', '2026-07-16 20:32:52'),
(33, 26, 'AJUSTE PRECIO', 0, 'Venta: de 20 a 1.95', 'root', '2026-07-17 17:08:33'),
(34, 28, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-07-17 20:09:23'),
(35, 29, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-07-18 15:03:48'),
(36, 30, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-07-18 15:05:54'),
(37, 31, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-07-18 15:06:15'),
(38, 32, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-07-18 15:06:41'),
(39, 33, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-07-18 15:07:16'),
(40, 34, 'ENTRADA', 20, 'Registro Inicial (Unid)', 'root', '2026-07-18 15:07:38'),
(41, 35, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-07-18 15:08:10'),
(42, 36, 'ENTRADA', 20, 'Registro Inicial (Unid)', 'root', '2026-07-18 15:08:27'),
(43, 26, 'AJUSTE PRECIO', 0, 'Venta: de 1.95 a 1.96', 'root', '2026-07-18 15:08:42'),
(44, 37, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-07-20 18:34:31'),
(45, 26, 'ELIMINACIÓN', 0, 'ELIMINADO: Harina Pan', 'root', '2026-07-20 18:35:28'),
(46, 27, 'AJUSTE PRECIO', 0, 'Venta: de 6.6 a 6.8', 'root', '2026-07-20 18:35:41'),
(47, 27, 'AJUSTE PRECIO', 0, 'Venta: de 6.8 a 6.9', 'root', '2026-07-20 18:38:33'),
(48, 35, 'AJUSTE PRECIO', 0, 'Venta: de 1.44 a 1.45', 'root', '2026-07-20 18:48:46'),
(49, 38, 'ENTRADA', 100, 'Registro Inicial (Unid)', 'root', '2026-07-20 18:49:54'),
(50, 38, 'AJUSTE PRECIO', 0, 'Venta: de 1100 a 1101', 'root', '2026-07-20 18:50:02'),
(51, 38, 'AJUSTE PRECIO', 0, 'Venta: de 1101 a 1200', 'root', '2026-07-25 22:18:50'),
(52, 32, 'VENTA', 1, 'Venta Factura #19 - Producto: Caramelos', 'root', '2026-07-29 01:04:46'),
(53, 39, 'ENTRADA', 20, 'Registro Inicial (Unid)', 'root', '2026-08-12 04:26:07'),
(54, 40, 'ENTRADA', 20, 'Registro Inicial (Unid)', 'root', '2026-08-12 04:26:58'),
(55, 39, 'VENTA', 1, 'Venta Factura #20 - Producto: HARINA DE TRIGO', 'root', '2026-08-17 02:31:02'),
(56, 39, 'VENTA', 1, 'Venta Factura #21 - Producto: HARINA DE TRIGO', 'root', '2026-08-17 02:31:56'),
(57, 27, 'VENTA', 0, 'Venta Factura #21 - Producto: Queso', 'root', '2026-08-17 02:31:56'),
(58, 27, 'VENTA', 0, 'Venta Factura #22 - Producto: Queso', 'root', '2026-08-17 02:33:11'),
(59, 27, 'VENTA', 0, 'Venta Factura #23 - Producto: Queso', 'root', '2026-08-17 02:41:54'),
(60, 27, 'VENTA', 0, 'Venta Factura #24 - Producto: Queso', 'root', '2026-08-17 02:42:34'),
(61, 27, 'VENTA', 0, 'Venta Factura #25 - Producto: Queso', 'root', '2026-08-17 02:43:19'),
(62, 27, 'VENTA', 0, 'Venta Factura #26 - Producto: Queso', 'root', '2026-08-17 02:48:12'),
(63, 27, 'VENTA', 0, 'Venta Factura #27 - Producto: Queso', 'root', '2026-08-17 02:49:37'),
(64, 27, 'VENTA', 0, 'Venta Factura #28 - Producto: Queso', 'root', '2026-08-17 02:51:03'),
(65, 27, 'VENTA', 0, 'Venta Factura #29 - Producto: Queso', 'root', '2026-08-17 03:06:46'),
(66, 27, 'VENTA', 0, 'Venta Factura #30 - Producto: Queso', 'root', '2026-08-17 03:15:10'),
(67, 31, 'VENTA', 1, 'Venta Factura #31 - Producto: Marcador (Cashea)', 'root', '2026-08-17 03:17:02'),
(68, 32, 'VENTA', 1, 'Venta Factura #32 - Producto: Caramelos', 'root', '2026-08-17 03:17:37'),
(69, 29, 'VENTA', 1, 'Venta Factura #33 - Producto: cesta', 'root', '2026-08-17 03:19:06'),
(70, 39, 'VENTA', 1, 'Venta Factura #34 - Producto: HARINA DE TRIGO', 'root', '2026-08-19 15:29:04'),
(71, 35, 'VENTA', 1, 'Venta Factura #35 - Producto: agua mineral', 'root', '2026-09-01 01:53:59'),
(72, 36, 'VENTA', 1, 'Venta Factura #36 - Producto: refresco', 'root', '2026-09-01 02:06:33'),
(73, 35, 'VENTA', 1, 'Venta Factura #37 - Producto: agua mineral', 'root', '2026-09-01 02:07:20'),
(74, 31, 'VENTA', 1, 'Venta Factura #38 - Producto: Marcador', 'root', '2026-09-01 02:19:18'),
(75, 35, 'VENTA', 1, 'Venta Factura #39 - Producto: agua mineral', 'root', '2026-09-01 02:24:32'),
(76, 39, 'VENTA', 1, 'Venta Factura #40 - Producto: HARINA DE TRIGO', 'root', '2026-09-01 02:31:22'),
(77, 40, 'VENTA', 1, 'Venta Factura #41 - Producto: arrroz mary', 'root', '2026-09-01 02:34:01'),
(78, 32, 'VENTA', 1, 'Venta Factura #42 - Producto: Caramelos', 'root', '2026-09-01 02:34:22'),
(79, 27, 'VENTA', 0, 'Venta Factura #43 - Producto: Queso', 'root', '2026-09-01 02:41:03'),
(80, 27, 'VENTA', 0, 'Venta Factura #44 - Producto: Queso', 'root', '2026-09-01 02:41:57'),
(81, 27, 'VENTA', 0, 'Venta Factura #45 - Producto: Queso', 'root', '2026-09-01 02:42:21'),
(82, 27, 'VENTA', 0, 'Venta Factura #46 - Producto: Queso', 'root', '2026-09-01 02:42:53'),
(83, 27, 'VENTA', 0, 'Venta Factura #47 - Producto: Queso', 'root', '2026-09-01 02:43:15'),
(84, 27, 'VENTA', 0, 'Venta Factura #48 - Producto: Queso', 'root', '2026-09-01 03:08:00'),
(85, 27, 'VENTA', 0, 'Venta Factura #49 - Producto: Queso', 'root', '2026-09-01 03:54:35'),
(86, 27, 'VENTA', 0, 'Venta Factura #50 - Producto: Queso', 'root', '2026-09-01 03:57:52'),
(87, 27, 'VENTA', 0, 'Venta Factura #51 - Producto: Queso', 'root', '2026-09-01 04:02:52'),
(88, 27, 'VENTA', 0, 'Venta Factura #52 - Producto: Queso', 'root', '2026-09-01 04:06:36'),
(89, 41, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-09-03 03:00:58'),
(90, 42, 'ENTRADA', 10, 'Registro Inicial (Unid)', 'root', '2026-09-03 03:02:01'),
(91, 43, 'ENTRADA', 15, 'Registro Inicial (Unid)', 'root', '2026-09-03 03:29:29'),
(92, 39, 'VENTA', 1, 'Venta Factura #53 - Producto: HARINA DE TRIGO', 'root', '2026-10-10 15:47:22'),
(93, 34, 'VENTA', 2, 'Venta Factura #54 - Producto: hojas blancas (Cashea)', 'root', '2026-11-05 22:02:55'),
(94, 29, 'VENTA', 1, 'Venta Factura #54 - Producto: cesta (Cashea)', 'root', '2026-11-05 22:02:55'),
(95, 27, 'VENTA', 5, 'Venta Factura #54 - Producto: Queso (Cashea)', 'root', '2026-11-05 22:02:55'),
(96, 32, 'VENTA', 1, 'Venta Factura #55 - Producto: Caramelos', 'root', '2026-11-15 20:37:58'),
(97, 42, 'VENTA', 1, 'Venta Factura #55 - Producto: crema dental', 'root', '2026-11-15 20:37:58'),
(98, 39, 'VENTA', 1, 'Venta Factura #55 - Producto: HARINA DE TRIGO', 'root', '2026-11-15 20:37:58'),
(99, 43, 'VENTA', 1, 'Venta Factura #56 - Producto: otra crema', 'root', '2026-11-15 20:40:04'),
(100, 42, 'VENTA', 1, 'Venta Factura #56 - Producto: crema dental', 'root', '2026-11-15 20:40:04'),
(101, 41, 'VENTA', 1, 'Venta Factura #56 - Producto: Gelatina', 'root', '2026-11-15 20:40:04'),
(102, 40, 'VENTA', 1, 'Venta Factura #57 - Producto: arrroz mary', 'root', '2026-11-15 20:44:28'),
(103, 39, 'VENTA', 1, 'Venta Factura #57 - Producto: HARINA DE TRIGO', 'root', '2026-11-15 20:44:28');

-- --------------------------------------------------------

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
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `codigo_barras`, `nombre`, `precio_costo`, `precio`, `stock`, `tipo_venta`, `unidad_medida`, `id_departamento`, `usa_inventario`, `moneda`, `moneda_base`, `precio_mayor`, `cantidad_mayor`, `precio_bulto`, `cantidad_bulto`, `id_proveedor`, `usa_parametros_fiscales`) VALUES
(27, '47458821', 'Queso', '6.00', 7.935, '8.320', 'granel', 'KG', 1, 1, 'USD', NULL, '0.00', 0, '0.00', 0, 3, 1),
(28, '2145156963222', 'arroz', '1.90', 3.2665749999999996, '10.000', 'unidad', 'Unid', NULL, 1, 'USD', NULL, '0.00', 0, '0.00', 0, NULL, 1),
(29, '30079226', 'cesta', '2.00', 2.76, '8.000', 'unidad', 'Unid', NULL, 1, 'USD', NULL, '0.00', 0, '0.00', 0, NULL, 1),
(30, '741369545454', 'teclado', '6.00', 8.28, '10.000', 'unidad', 'Unid', NULL, 1, 'USD', NULL, '0.00', 0, '0.00', 0, NULL, 1),
(31, '3434342354646', 'Marcador', '1.50', 2.07, '8.000', 'unidad', 'Unid', NULL, 1, 'USD', NULL, '0.00', 0, '0.00', 0, NULL, 1),
(32, '48481185162', 'Caramelos', '2.50', 3.4499999999999997, '6.000', 'unidad', 'Unid', NULL, 1, 'USD', NULL, '0.00', 0, '0.00', 0, NULL, 1),
(33, '551515147481', 'lapicero', '2.50', 3.4499999999999997, '10.000', 'unidad', 'Unid', NULL, 1, 'USD', NULL, '0.00', 0, '0.00', 0, NULL, 1),
(34, '54518121254', 'hojas blancas', '0.50', 0.69, '18.000', 'unidad', 'Unid', NULL, 1, 'USD', NULL, '0.00', 0, '0.00', 0, NULL, 1),
(35, '656532154', 'agua mineral', '1.20', 1.6674999999999998, '7.000', 'unidad', 'Unid', NULL, 1, 'USD', NULL, '0.00', 0, '0.00', 0, NULL, 1),
(36, '63215478', 'refresco', '1.00', 1.38, '19.000', 'unidad', 'Unid', NULL, 1, 'USD', NULL, '0.00', 0, '0.00', 0, NULL, 1),
(37, '541521215454', 'Salsa de Tomate', '2.20', 3.036, '10.000', 'unidad', 'Unid', NULL, 1, 'USD', NULL, '0.00', 0, '0.00', 0, 2, 1),
(38, '545451212', 'arroz', '1.60', 1380, '100.000', 'unidad', 'Unid', NULL, 1, '0', NULL, '0.00', 0, '0.00', 0, NULL, 1),
(39, 'productoejemplo1', 'HARINA DE TRIGO', '1.80', 2.16, '14.000', 'unidad', 'Unid', NULL, 1, 'USD', NULL, '0.00', 0, '0.00', 0, 2, 1),
(40, '5788', 'arrroz mary', '1.80', 2.07, '18.000', 'unidad', 'Unid', NULL, 1, '0', NULL, '0.00', 0, '0.00', 0, 2, 0),
(41, '7596273000814', 'Gelatina', '1.50', 1.95, '9.000', 'unidad', 'Unid', NULL, 1, '0', NULL, '0.00', 0, '0.00', 0, NULL, 0),
(42, '7597257001674', 'crema dental', '1.80', 2.16, '8.000', 'unidad', 'Unid', NULL, 1, '0', NULL, '0.00', 0, '0.00', 0, NULL, 0),
(43, '7598612100049', 'otra crema', '0.50', 0.75, '14.000', 'unidad', 'Unid', NULL, 1, '0', NULL, '0.00', 0, '0.00', 0, NULL, 0);

-- --------------------------------------------------------

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

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id`, `nombre_empresa`, `rif_cedula`, `telefono`, `direccion`, `fecha_registro`) VALUES
(2, 'Polar', 'J-546321547', '04123828799', 'Caracas', '2026-07-10 17:42:23'),
(3, 'LLANO', '2557488215', '04142255786', 'santa teresa', '2026-07-11 15:49:42');

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
-- Volcado de datos para la tabla `registro_pago_movil`
--

INSERT INTO `registro_pago_movil` (`id`, `id_venta`, `banco_origen`, `referencia`, `monto_bs`, `usuario`, `fecha`) VALUES
(1, 39, 'No especificado', 'S/N', '1312.87', 'root', '2026-08-31 22:24:32'),
(2, 40, 'No especificado', 'S/N', '1700.63', 'root', '2026-08-31 22:31:22'),
(3, 41, 'No especificado', 'S/N', '1629.77', 'root', '2026-08-31 22:34:01'),
(4, 42, '0102 - Banco de Venezuela', '1212', '2716.28', 'root', '2026-08-31 22:34:22'),
(5, 44, '0102 - Banco de Venezuela', '1584', '618.49', 'root', '2026-08-31 22:41:57'),
(6, 53, '0102 - Banco de Venezuela', '4569', '1700.63', 'root', '2026-10-10 11:47:22');

-- --------------------------------------------------------

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
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `password`, `nombre`, `rol`, `fecha_creacion`, `p_pos`, `p_inventario`, `p_facturas`, `p_corte`, `p_config`, `permisos`, `estado`) VALUES
(2, 'admin', '1234', 'Administrador Principal', 'Admin', '2026-02-12 18:23:12', 1, 0, 0, 0, 0, NULL, 'Activo'),
(3, 'root', '27283989', NULL, 'Admin', '2026-02-12 18:25:19', 1, 0, 0, 0, 0, NULL, 'Activo'),
(15, 'cajero', '12345', NULL, 'Cajero', '2026-04-27 13:18:13', 1, 0, 0, 0, 0, '[\"ventas\",\"config\"]', 'Activo');

-- --------------------------------------------------------

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
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id`, `cliente`, `id_cliente`, `total`, `fecha`, `estado`, `metodo_pago`, `cashea_recargo`, `cashea_inicial`, `cashea_financiado_usd`, `cashea_metodo_inicial`, `pago_efectivo_usd`, `usuario`, `tasa_momento`, `pagada`, `pago_punto_bs`, `pago_efectivo_bs`, `pago_movil_bs`, `pago_biopago_bs`, `cambio_bs`, `cambio_usd`, `cashea_recargo_usd`, `cashea_inicial_usd`) VALUES
(43, 'Público General', NULL, '0.78', '2026-08-31 22:41:03', 'PAGADA', 'Efectivo Bs.', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '787.33', 0, '0.00', '618.49', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00'),
(44, 'Público General', NULL, '0.78', '2026-08-31 22:41:57', 'PAGADA', 'Pago Móvil', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '787.33', 0, '0.00', '0.00', '618.49', '0.00', '0.00', '0.00', '0.00', '0.00'),
(45, 'Público General', NULL, '0.78', '2026-08-31 22:42:21', 'PAGADA', 'Punto', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '787.33', 0, '618.49', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00'),
(46, 'Público General', NULL, '0.78', '2026-08-31 22:42:53', 'PAGADA', 'Multiple', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '787.33', 0, '300.00', '0.00', '0.00', '318.50', '0.00', '0.00', '0.00', '0.00'),
(47, 'Público General', NULL, '0.00', '2026-08-31 22:43:15', '', 'BioPago', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '787.33', 0, '0.00', '0.00', '0.00', '618.49', '0.00', '0.00', '0.00', '0.00'),
(48, 'Público General', NULL, '0.00', '2026-08-31 23:08:00', '', 'Punto', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '787.33', 0, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00'),
(49, 'Público General', NULL, '0.78', '2026-08-31 23:54:35', 'PAGADA', 'BioPago', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '787.33', 0, '0.00', '0.00', '0.00', '618.49', '0.00', '0.00', '0.00', '0.00'),
(50, 'Público General', NULL, '0.78', '2026-08-31 23:57:52', 'PAGADA', 'BioPago', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '787.33', 0, '0.00', '0.00', '0.00', '618.49', '0.00', '0.00', '0.00', '0.00'),
(51, 'Público General', NULL, '0.78', '2026-09-01 00:02:52', 'PAGADA', 'BioPago', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '787.33', 0, '0.00', '0.00', '0.00', '618.49', '0.00', '0.00', '0.00', '0.00'),
(52, 'Público General', NULL, '0.78', '2026-09-01 00:06:36', 'PAGADA', 'BioPago', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '787.33', 0, '0.00', '0.00', '0.00', '618.49', '0.00', '0.00', '0.00', '0.00'),
(53, 'Público General', NULL, '2.16', '2026-10-10 11:47:22', 'PAGADA', 'Pago Móvil', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '787.33', 0, '0.00', '0.00', '1700.63', '0.00', '0.00', '0.00', '0.00', '0.00'),
(54, 'Público General', NULL, '43.81', '2026-11-05 18:02:55', 'PAGADA', 'Cashea', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '876.36', 0, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00'),
(55, 'Público General', NULL, '7.77', '2026-11-15 16:37:58', 'PAGADA', 'Punto', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '876.36', 0, '6809.31', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00'),
(56, 'Público General', NULL, '4.86', '2026-11-15 16:40:04', 'PAGADA', 'Punto', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '876.36', 0, '4259.10', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00'),
(57, 'Público General', NULL, '4.90', '2026-11-15 16:44:28', 'PAGADA', 'Punto', '0.00', '0.00', '0.00', NULL, '0.00', 'root', '876.36', 0, '4300.12', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00');

-- --------------------------------------------------------

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

--
-- Volcado de datos para la tabla `ventas_cashea`
--

INSERT INTO `ventas_cashea` (`id`, `id_venta`, `monto_recargo`, `monto_inicial`, `monto_financiado`, `fecha_registro`) VALUES
(14, 54, '0.00', '25.00', '18.81', '0000-00-00 00:00:00');

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
