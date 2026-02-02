-- Reiniciar BBDD: Drop and Create Tables

DROP TABLE IF EXISTS observaciones_facilitador;
DROP TABLE IF EXISTS tests_usabilidad;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    profesion VARCHAR(100),
    edad TINYINT UNSIGNED,
    genero ENUM('Masculino','Femenino'),
    pericia_informatica ENUM('Baja','Media','Alta')
);

CREATE TABLE tests_usabilidad (
    id_test INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT,
    dispositivo ENUM('Ordenador','Tableta','Teléfono'),
    duracion_segundos INT UNSIGNED,
    completado BOOLEAN,
    comentarios_usuario TEXT,
    propuestas_mejora TEXT,
    valoracion TINYINT UNSIGNED,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

CREATE TABLE observaciones_facilitador (
    id_observacion INT PRIMARY KEY AUTO_INCREMENT,
    id_test INT,
    comentarios_facilitador TEXT,
    FOREIGN KEY (id_test) REFERENCES tests_usabilidad(id_test)
);
