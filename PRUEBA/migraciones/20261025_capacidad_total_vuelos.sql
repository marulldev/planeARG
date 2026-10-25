-- Ejecutar una sola vez en la base activa antes de usar el reporte de vuelos.
ALTER TABLE vuelos
  ADD COLUMN capacidadTotal INT NOT NULL DEFAULT 0 AFTER asientosDisponibles;

UPDATE vuelos AS v
SET capacidadTotal = v.asientosDisponibles + (
  SELECT COUNT(*)
  FROM reservas AS r
  WHERE r.codVuelo = v.codVuelo
    AND r.estadoReserva <> 'cancelada'
);

ALTER TABLE vuelos
  MODIFY COLUMN capacidadTotal INT NOT NULL;
