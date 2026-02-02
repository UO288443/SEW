CREATE DATABASE IF NOT EXISTS `uo288443_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `uo288443_db`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS observaciones_facilitador;
DROP TABLE IF EXISTS tests_usabilidad;
DROP TABLE IF EXISTS usuarios;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    profesion VARCHAR(100) NOT NULL,
    edad TINYINT UNSIGNED NOT NULL,
    genero ENUM('Masculino', 'Femenino') NOT NULL,
    pericia_informatica ENUM('Baja', 'Media', 'Alta') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tests_usabilidad (
    id_test INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    dispositivo ENUM('Ordenador', 'Tableta', 'Teléfono') NOT NULL,
    duracion_segundos INT UNSIGNED NOT NULL,
    completado BOOLEAN NOT NULL,
    comentarios_usuario TEXT,
    propuestas_mejora TEXT,
    valoracion TINYINT UNSIGNED NOT NULL,
    CONSTRAINT chk_valoracion_rango CHECK (valoracion BETWEEN 0 AND 10),
    CONSTRAINT fk_tests_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE observaciones_facilitador (
    id_observacion INT AUTO_INCREMENT PRIMARY KEY,
    id_test INT NOT NULL,
    comentarios_facilitador TEXT NOT NULL,
    CONSTRAINT fk_obs_test
        FOREIGN KEY (id_test) REFERENCES tests_usabilidad(id_test)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
