-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: db
-- Tiempo de generación: 03-02-2026 a las 12:05:27
-- Versión del servidor: 5.7.44
-- Versión de PHP: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `finanzas_app`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `CUENTAS`
--

CREATE TABLE `CUENTAS` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `saldo_inicial` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `CUENTAS`
--

INSERT INTO `CUENTAS` (`id`, `usuario_id`, `nombre`, `saldo_inicial`) VALUES
(1, 1, 'Cuenta Banco', 1500.00),
(2, 1, 'Efectivo / Cartera', 50.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `MOVIMIENTOS`
--

CREATE TABLE `MOVIMIENTOS` (
  `id` int(11) NOT NULL,
  `cuenta_id` int(11) NOT NULL,
  `tipo_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha` date NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `MOVIMIENTOS`
--

INSERT INTO `MOVIMIENTOS` (`id`, `cuenta_id`, `tipo_id`, `monto`, `fecha`, `descripcion`) VALUES
(1, 1, 1, 1200.00, '2023-10-01', 'Nómina Octubre'),
(2, 1, 2, 400.00, '2023-10-05', 'Pago Alquiler'),
(3, 2, 3, 15.50, '2023-10-06', 'Cena Hamburguesas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `MOVIMIENTOS_RECURRENTES`
--

CREATE TABLE `MOVIMIENTOS_RECURRENTES` (
  `id` int(11) NOT NULL,
  `cuenta_id` int(11) NOT NULL,
  `tipo_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `dia_cargo` int(11) NOT NULL,
  `periodicidad` enum('MENSUAL','ANUAL','SEMANAL') DEFAULT 'MENSUAL',
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `MOVIMIENTOS_RECURRENTES`
--

INSERT INTO `MOVIMIENTOS_RECURRENTES` (`id`, `cuenta_id`, `tipo_id`, `monto`, `dia_cargo`, `periodicidad`, `activo`) VALUES
(1, 1, 2, 400.00, 5, 'MENSUAL', 1),
(2, 1, 4, 12.99, 15, 'MENSUAL', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TIPOS_TRANSACCION`
--

CREATE TABLE `TIPOS_TRANSACCION` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `naturaleza` enum('INGRESO','GASTO') NOT NULL,
  `icono` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `TIPOS_TRANSACCION`
--

INSERT INTO `TIPOS_TRANSACCION` (`id`, `nombre`, `naturaleza`, `icono`) VALUES
(1, 'Nómina', 'INGRESO', 'fa-money-bill'),
(2, 'Alquiler', 'GASTO', 'fa-home'),
(3, 'Comida', 'GASTO', 'fa-burger'),
(4, 'Netflix', 'GASTO', 'fa-film');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `USUARIOS`
--

CREATE TABLE `USUARIOS` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `DNI` varchar(9) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `USUARIOS`
--

INSERT INTO `USUARIOS` (`id`, `nombre`, `email`, `password`, `DNI`) VALUES
(1, 'Carlos Estudiante', 'carlos@test.com', '123456', '12345678X');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `CUENTAS`
--
ALTER TABLE `CUENTAS`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `MOVIMIENTOS`
--
ALTER TABLE `MOVIMIENTOS`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cuenta_id` (`cuenta_id`),
  ADD KEY `tipo_id` (`tipo_id`);

--
-- Indices de la tabla `MOVIMIENTOS_RECURRENTES`
--
ALTER TABLE `MOVIMIENTOS_RECURRENTES`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cuenta_id` (`cuenta_id`),
  ADD KEY `tipo_id` (`tipo_id`);

--
-- Indices de la tabla `TIPOS_TRANSACCION`
--
ALTER TABLE `TIPOS_TRANSACCION`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `USUARIOS`
--
ALTER TABLE `USUARIOS`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `CUENTAS`
--
ALTER TABLE `CUENTAS`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `MOVIMIENTOS`
--
ALTER TABLE `MOVIMIENTOS`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `MOVIMIENTOS_RECURRENTES`
--
ALTER TABLE `MOVIMIENTOS_RECURRENTES`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `TIPOS_TRANSACCION`
--
ALTER TABLE `TIPOS_TRANSACCION`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `USUARIOS`
--
ALTER TABLE `USUARIOS`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `CUENTAS`
--
ALTER TABLE `CUENTAS`
  ADD CONSTRAINT `CUENTAS_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `USUARIOS` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `MOVIMIENTOS`
--
ALTER TABLE `MOVIMIENTOS`
  ADD CONSTRAINT `MOVIMIENTOS_ibfk_1` FOREIGN KEY (`cuenta_id`) REFERENCES `CUENTAS` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `MOVIMIENTOS_ibfk_2` FOREIGN KEY (`tipo_id`) REFERENCES `TIPOS_TRANSACCION` (`id`);

--
-- Filtros para la tabla `MOVIMIENTOS_RECURRENTES`
--
ALTER TABLE `MOVIMIENTOS_RECURRENTES`
  ADD CONSTRAINT `MOVIMIENTOS_RECURRENTES_ibfk_1` FOREIGN KEY (`cuenta_id`) REFERENCES `CUENTAS` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `MOVIMIENTOS_RECURRENTES_ibfk_2` FOREIGN KEY (`tipo_id`) REFERENCES `TIPOS_TRANSACCION` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
