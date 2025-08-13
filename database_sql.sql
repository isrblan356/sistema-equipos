-- Base de datos para el sistema de revisión de equipos
CREATE DATABASE IF NOT EXISTS sistema_equipos;
USE sistema_equipos;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de tipos de equipo
CREATE TABLE tipos_equipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion TEXT
);

-- Insertar tipos de equipo
INSERT INTO tipos_equipo (nombre, descripcion) VALUES 
('Antena Ubiquiti', 'Equipos de antenas Ubiquiti'),
('Router TP-LINK', 'Routers de la marca TP-LINK');

-- Tabla de equipos
CREATE TABLE equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    marca VARCHAR(50) NOT NULL,
    numero_serie VARCHAR(100) UNIQUE NOT NULL,
    tipo_equipo_id INT,
    ubicacion VARCHAR(200),
    ip_address VARCHAR(15),
    estado ENUM('Activo', 'Inactivo', 'Mantenimiento', 'Dañado') DEFAULT 'Activo',
    fecha_instalacion DATE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_registro_id INT,
    FOREIGN KEY (tipo_equipo_id) REFERENCES tipos_equipo(id),
    FOREIGN KEY (usuario_registro_id) REFERENCES usuarios(id)
);

-- Tabla de revisiones
CREATE TABLE revisiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT,
    usuario_id INT,
    fecha_revision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado_revision ENUM('Excelente', 'Bueno', 'Regular', 'Malo', 'Crítico') NOT NULL,
    observaciones TEXT,
    temperatura DECIMAL(5,2),
    voltaje DECIMAL(5,2),
    señal_dbm DECIMAL(5,2),
    velocidad_mbps DECIMAL(8,2),
    tiempo_actividad_horas INT,
    problemas_detectados TEXT,
    acciones_realizadas TEXT,
    requiere_mantenimiento BOOLEAN DEFAULT FALSE,
    fecha_proximo_mantenimiento DATE,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Insertar usuario administrador por defecto (password: admin123)
INSERT INTO usuarios (nombre, email, password) VALUES 
('Administrador', 'admin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insertar algunos equipos de ejemplo
INSERT INTO equipos (nombre, modelo, marca, numero_serie, tipo_equipo_id, ubicacion, ip_address, estado, fecha_instalacion, usuario_registro_id) VALUES
('Antena Sector Norte', 'airMAX AC', 'Ubiquiti', 'UB001234567', 1, 'Torre Norte - Piso 15', '192.168.1.100', 'Activo', '2024-01-15', 1),
('Router Principal', 'Archer C80', 'TP-LINK', 'TPL987654321', 2, 'Sala de Servidores', '192.168.1.1', 'Activo', '2024-02-01', 1),
('Antena PtP Este', 'PowerBeam 5AC', 'Ubiquiti', 'UB567890123', 1, 'Edificio Este - Azotea', '192.168.1.101', 'Activo', '2024-01-20', 1);

-- Insertar algunas revisiones de ejemplo
INSERT INTO revisiones (equipo_id, usuario_id, estado_revision, observaciones, temperatura, voltaje, señal_dbm, velocidad_mbps, tiempo_actividad_horas, requiere_mantenimiento) VALUES
(1, 1, 'Bueno', 'Funcionamiento normal, señal estable', 45.5, 24.2, -65.0, 150.5, 720, FALSE),
(2, 1, 'Excelente', 'Router funcionando perfectamente', 38.2, 12.0, NULL, 300.0, 1440, FALSE),
(3, 1, 'Regular', 'Señal algo débil, requiere ajuste', 52.1, 24.0, -72.5, 98.3, 480, TRUE);