-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: db
-- Tiempo de generación: 28-04-2026 a las 09:46:42
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
  `saldo_inicial` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
  `activo` tinyint(1) DEFAULT '1',
  `fecha_especifica` date DEFAULT NULL,
  `ultima_ejecucion` date DEFAULT NULL,
  `proxima_ejecucion` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `USUARIOS`
--

CREATE TABLE `USUARIOS` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `DNI` varchar(9) NOT NULL,
  `banco` varchar(50) NOT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `PASSWORD_RESETS`
--

CREATE TABLE `PASSWORD_RESETS` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TWO_FACTOR_CODES`
--

CREATE TABLE `TWO_FACTOR_CODES` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code` varchar(6) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
-- Indices de la tabla `PASSWORD_RESETS`
--
ALTER TABLE `PASSWORD_RESETS`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `token` (`token`);

--
-- Indices de la tabla `TWO_FACTOR_CODES`
--
ALTER TABLE `TWO_FACTOR_CODES`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `CUENTAS`
--
ALTER TABLE `CUENTAS`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `MOVIMIENTOS`
--
ALTER TABLE `MOVIMIENTOS`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `MOVIMIENTOS_RECURRENTES`
--
ALTER TABLE `MOVIMIENTOS_RECURRENTES`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `TIPOS_TRANSACCION`
--
ALTER TABLE `TIPOS_TRANSACCION`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `USUARIOS`
--
ALTER TABLE `USUARIOS`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `PASSWORD_RESETS`
--
ALTER TABLE `PASSWORD_RESETS`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `TWO_FACTOR_CODES`
--
ALTER TABLE `TWO_FACTOR_CODES`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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

--
-- Filtros para la tabla `TWO_FACTOR_CODES`
--
ALTER TABLE `TWO_FACTOR_CODES`
  ADD CONSTRAINT `TWO_FACTOR_CODES_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `USUARIOS` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
