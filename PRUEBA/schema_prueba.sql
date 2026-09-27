CREATE DATABASE IF NOT EXISTS planearg_prueba;
USE planearg_prueba;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    rol VARCHAR(50) DEFAULT 'pasajero',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vuelos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origen VARCHAR(100) NOT NULL,
    destino VARCHAR(100) NOT NULL,
    fecha_salida DATE NOT NULL,
    precio DECIMAL(10,2) NOT NULL
);

INSERT INTO usuarios (nombre, email, rol) VALUES
('Ana García', 'ana@mail.com', 'pasajero'),
('Luis Pérez', 'luis@mail.com', 'admin'),
('María López', 'maria@mail.com', 'pasajero');

INSERT INTO vuelos (origen, destino, fecha_salida, precio) VALUES
('Buenos Aires', 'Córdoba', '2026-10-10', 18500.00),
('Córdoba', 'Mendoza', '2026-10-12', 22000.00),
('Rosario', 'Salta', '2026-10-15', 27000.00);
