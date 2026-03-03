-- Script de migración para añadir campos a MOVIMIENTOS_RECURRENTES
-- Ejecutar este script en la base de datos

-- Añadir nuevos campos
ALTER TABLE MOVIMIENTOS_RECURRENTES 
ADD COLUMN fecha_especifica DATE NULL COMMENT 'Fecha específica para pagos anuales/semestrales',
ADD COLUMN ultima_ejecucion DATE NULL COMMENT 'Última vez que se ejecutó el pago automático',
ADD COLUMN proxima_ejecucion DATE NULL COMMENT 'Próxima fecha de ejecución calculada';

-- Modificar el ENUM de periodicidad para incluir SEMESTRAL
ALTER TABLE MOVIMIENTOS_RECURRENTES 
MODIFY COLUMN periodicidad ENUM('SEMANAL', 'MENSUAL', 'SEMESTRAL', 'ANUAL') DEFAULT 'MENSUAL';

-- Actualizar fechas de próxima ejecución para registros existentes (mensuales)
-- Esto es opcional, el sistema las calculará automáticamente
UPDATE MOVIMIENTOS_RECURRENTES 
SET proxima_ejecucion = DATE_ADD(CURDATE(), INTERVAL (dia_cargo - DAY(CURDATE())) DAY)
WHERE periodicidad = 'MENSUAL' AND proxima_ejecucion IS NULL;
