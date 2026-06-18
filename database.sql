CREATE DATABASE IF NOT EXISTS mundial2026;
USE mundial2026;

CREATE TABLE IF NOT EXISTS equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    grupo CHAR(1) NOT NULL,
    puntos INT DEFAULT 0,
    pj INT DEFAULT 0,
    pg INT DEFAULT 0,
    pe INT DEFAULT 0,
    pp INT DEFAULT 0,
    gf INT DEFAULT 0,
    gc INT DEFAULT 0,
    dg INT DEFAULT 0,
    eliminado TINYINT(1) DEFAULT 0,
    flag_url VARCHAR(255) DEFAULT ''
);

CREATE TABLE IF NOT EXISTS partidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo CHAR(1) DEFAULT NULL,
    equipo_local_id INT DEFAULT NULL,
    equipo_visitante_id INT DEFAULT NULL,
    goles_local INT DEFAULT NULL,
    goles_visitante INT DEFAULT NULL,
    fase ENUM('grupos','octavos','cuartos','semis','tercero','final') DEFAULT 'grupos',
    ronda VARCHAR(30) DEFAULT NULL,
    jugado TINYINT(1) DEFAULT 0,
    ganador_id INT DEFAULT NULL,
    perdedor_id INT DEFAULT NULL,
    FOREIGN KEY (equipo_local_id) REFERENCES equipos(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_visitante_id) REFERENCES equipos(id) ON DELETE CASCADE
);

INSERT INTO equipos (nombre, grupo) VALUES
('Argentina', 'A'), ('Chile', 'A'), ('México', 'A'), ('Nueva Zelanda', 'A'),
('Brasil', 'B'), ('España', 'B'), ('Japón', 'B'), ('Ghana', 'B'),
('Francia', 'C'), ('Inglaterra', 'C'), ('Estados Unidos', 'C'), ('Australia', 'C'),
('Alemania', 'D'), ('Países Bajos', 'D'), ('Senegal', 'D'), ('Corea del Sur', 'D');

INSERT INTO partidos (grupo, equipo_local_id, equipo_visitante_id, fase) VALUES
('A', 1, 2, 'grupos'), ('A', 3, 4, 'grupos'),
('A', 1, 3, 'grupos'), ('A', 2, 4, 'grupos'),
('A', 1, 4, 'grupos'), ('A', 2, 3, 'grupos'),
('B', 5, 6, 'grupos'), ('B', 7, 8, 'grupos'),
('B', 5, 7, 'grupos'), ('B', 6, 8, 'grupos'),
('B', 5, 8, 'grupos'), ('B', 6, 7, 'grupos'),
('C', 9, 10, 'grupos'), ('C', 11, 12, 'grupos'),
('C', 9, 11, 'grupos'), ('C', 10, 12, 'grupos'),
('C', 9, 12, 'grupos'), ('C', 10, 11, 'grupos'),
('D', 13, 14, 'grupos'), ('D', 15, 16, 'grupos'),
('D', 13, 15, 'grupos'), ('D', 14, 16, 'grupos'),
('D', 13, 16, 'grupos'), ('D', 14, 15, 'grupos');
