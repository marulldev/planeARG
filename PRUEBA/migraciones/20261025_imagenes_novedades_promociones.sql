-- Ejecutar una sola vez en la base activa para habilitar imágenes opcionales.
ALTER TABLE novedades
  ADD COLUMN imagenNovedad VARCHAR(255) NULL AFTER textoNovedad;

ALTER TABLE promociones
  ADD COLUMN imagenPromocion VARCHAR(255) NULL AFTER descripcionPromocion;
