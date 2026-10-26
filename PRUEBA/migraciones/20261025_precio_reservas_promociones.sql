-- Ejecutar una sola vez en la base activa antes de aplicar promociones a reservas.
ALTER TABLE reservas
  ADD COLUMN precioReserva DECIMAL(10,2) NULL AFTER codVuelo;

UPDATE reservas AS r
INNER JOIN vuelos AS v ON v.codVuelo = r.codVuelo
SET r.precioReserva = v.precioVuelo
WHERE r.precioReserva IS NULL;

ALTER TABLE reservas
  MODIFY COLUMN precioReserva DECIMAL(10,2) NOT NULL;
