-- Ejecutar una sola vez en la base activa antes de crear promociones con expiración.
-- Las promociones existentes quedan sin fecha de expiración definida.
ALTER TABLE promociones
  ADD COLUMN fechaExpiracionPromocion DATE NULL AFTER codAerolinea;
