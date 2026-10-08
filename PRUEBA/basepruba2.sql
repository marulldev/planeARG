CREATE DATABASE planearg CHARACTER SET utf8mb4;
USE planearg;

CREATE TABLE usuarios (
  codUsuario INT AUTO_INCREMENT PRIMARY KEY,
  nombreUsuario VARCHAR(100) NOT NULL,
  claveUsuario VARCHAR(255) NOT NULL,
  tipoUsuario ENUM('administrador','ceo de aerolinea','usuario') NOT NULL,
  emailUsuario VARCHAR(100) NOT NULL,
  telefonoUsuario VARCHAR(20)
);

CREATE TABLE aerolineas (
  codAerolinea INT AUTO_INCREMENT PRIMARY KEY,
  nombreAerolinea VARCHAR(100) NOT NULL,
  codigoIATA VARCHAR(3) NOT NULL,
  descripcionAerolinea VARCHAR(200),
  codPais VARCHAR(3)
);

CREATE TABLE vuelos (
  codVuelo INT AUTO_INCREMENT PRIMARY KEY,
  codAerolinea INT NOT NULL,
  origenVuelo VARCHAR(50) NOT NULL,
  destinoVuelo VARCHAR(50) NOT NULL,
  fechaSalidaVuelo DATE NOT NULL,
  horaSalidaVuelo TIME NOT NULL,
  precioVuelo DECIMAL(10,2) NOT NULL,
  asientosDisponibles INT NOT NULL,
  FOREIGN KEY (codAerolinea) REFERENCES aerolineas(codAerolinea)
);

CREATE TABLE promociones (
  codPromocion INT AUTO_INCREMENT PRIMARY KEY,
  descripcionPromocion VARCHAR(200),
  descuentoPromocion DECIMAL(5,2) NOT NULL,
  codAerolinea INT NOT NULL,
  estadoPromocion ENUM('pendiente','aprobada','denegada') NOT NULL DEFAULT 'pendiente',
  FOREIGN KEY (codAerolinea) REFERENCES aerolineas(codAerolinea)
);

CREATE TABLE novedades (
  codNovedad INT AUTO_INCREMENT PRIMARY KEY,
  textoNovedad VARCHAR(200) NOT NULL,
  fechaPublicacionNovedad DATE NOT NULL,
  fechaExpiracionNovedad DATE
);

CREATE TABLE reservas (
  codReserva INT AUTO_INCREMENT PRIMARY KEY,
  codUsuario INT NOT NULL,
  codVuelo INT NOT NULL,
  fechaReserva DATE NOT NULL,
  estadoReserva ENUM('pendiente de pago','confirmada','cancelada') NOT NULL DEFAULT 'pendiente de pago',
  FOREIGN KEY (codUsuario) REFERENCES usuarios(codUsuario),
  FOREIGN KEY (codVuelo) REFERENCES vuelos(codVuelo)
);