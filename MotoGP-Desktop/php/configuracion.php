<?php
class Configruacion
{
    private string $dbHost = 'localhost';
    private string $dbName = 'uo288443_db';
    private string $dbUser = 'DBUSER2025';
    private string $dbPassword = 'DBPSWD2025';


    private function getConnection(bool $selectDb = true): mysqli
    {
        if ($selectDb) {
            $conn = @new mysqli($this->dbHost, $this->dbUser, $this->dbPassword, $this->dbName);
        } else {
            $conn = @new mysqli($this->dbHost, $this->dbUser, $this->dbPassword);
        }

        if ($conn->connect_error) {
            throw new Exception('Error de conexión: ' . $conn->connect_error);
        }

        $conn->set_charset('utf8mb4');
        return $conn;
    }

    public function crearBBDD(): string
    {
        $rutaSql = __DIR__ . '/crear_bbdd.sql'; 

        if (!file_exists($rutaSql)) {
            return 'No se ha encontrado el archivo SQL de creación (crear_bbdd.sql).';
        }

        $sql = file_get_contents($rutaSql);
        if ($sql === false || trim($sql) === '') {
            return 'El archivo crear_bbdd.sql está vacío o no se puede leer.';
        }

        try {
            $conn = $this->getConnection(false);

            if ($conn->multi_query($sql) === false) {
                $mensaje = 'Error al ejecutar el script de creación: ' . $conn->error;
            } else {
                // Consumir todos los resultados de multi_query
                do {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->more_results() && $conn->next_result());

                $mensaje = 'Base de datos creada correctamente.';
            }

            $conn->close();
            return $mensaje;
        } catch (Exception $e) {
            return 'Error al crear la BBDD: ' . $e->getMessage();
        }
    }


    public function guardarCSV(): void
    {
        try {
            $conn = $this->getConnection();

            $sql = "
                SELECT 
                    t.id_test,
                    u.id_usuario,
                    u.profesion,
                    u.edad,
                    u.genero,
                    u.pericia_informatica,
                    t.dispositivo,
                    t.duracion_segundos,
                    t.completado,
                    t.comentarios_usuario,
                    t.propuestas_mejora,
                    t.valoracion
                FROM tests_usabilidad t
                LEFT JOIN usuarios u ON t.id_usuario = u.id_usuario
                ORDER BY t.id_test
            ";

            $result = $conn->query($sql);
            if ($result === false) {
                throw new Exception('Error en la consulta: ' . $conn->error);
            }

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="tests_usabilidad.csv"');

            $output = fopen('php://output', 'w');

            // Cabecera de columnas
            fputcsv($output, [
                'id_test',
                'id_usuario',
                'profesion',
                'edad',
                'genero',
                'pericia_informatica',
                'dispositivo',
                'duracion_segundos',
                'completado',
                'comentarios_usuario',
                'propuestas_mejora',
                'valoracion'
            ]);

            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }

            fclose($output);
            $result->free();
            $conn->close();
            exit; 
        } catch (Exception $e) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Error al exportar CSV: ' . $e->getMessage();
            exit;
        }
    }

    /**
     * Importa el CSV generado por guardarCSV() y lo vuelca a la BBDD.
     * - Inserta/actualiza usuarios por id_usuario
     * - Inserta/actualiza tests_usabilidad por id_test
     */
    public function importarCSV(string $tmpFilePath): string
    {
        if (!is_file($tmpFilePath) || !is_readable($tmpFilePath)) {
            return 'No se puede leer el archivo CSV subido.';
        }

        $handle = fopen($tmpFilePath, 'r');
        if ($handle === false) {
            return 'No se ha podido abrir el CSV.';
        }

        // Leer cabecera
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return 'El CSV está vacío.';
        }

        $expectedHeader = [
            'id_test',
            'id_usuario',
            'profesion',
            'edad',
            'genero',
            'pericia_informatica',
            'dispositivo',
            'duracion_segundos',
            'completado',
            'comentarios_usuario',
            'propuestas_mejora',
            'valoracion'
        ];

        // Normalizar cabecera (por si hay BOM o espacios)
        $header = array_map(static function ($v) {
            $v = (string)$v;
            $v = preg_replace('/^\xEF\xBB\xBF/', '', $v); // BOM UTF-8
            return trim($v);
        }, $header);

        if ($header !== $expectedHeader) {
            fclose($handle);
            return 'La cabecera del CSV no coincide con el formato esperado.';
        }

        $conn = null;
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        try {
            $conn = $this->getConnection();
            $conn->begin_transaction();

            // Upsert usuario (asumiendo PK/UNIQUE en id_usuario)
            $sqlUsuario = "
                INSERT INTO usuarios
                    (id_usuario, profesion, edad, genero, pericia_informatica)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    profesion = VALUES(profesion),
                    edad = VALUES(edad),
                    genero = VALUES(genero),
                    pericia_informatica = VALUES(pericia_informatica)
            ";
            $stmtUsuario = $conn->prepare($sqlUsuario);
            if ($stmtUsuario === false) {
                throw new Exception('Error en prepare (usuarios): ' . $conn->error);
            }

            // Upsert test (asumiendo PK/UNIQUE en id_test)
            $sqlTest = "
                INSERT INTO tests_usabilidad
                    (id_test, id_usuario, dispositivo, duracion_segundos, completado, comentarios_usuario, propuestas_mejora, valoracion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    id_usuario = VALUES(id_usuario),
                    dispositivo = VALUES(dispositivo),
                    duracion_segundos = VALUES(duracion_segundos),
                    completado = VALUES(completado),
                    comentarios_usuario = VALUES(comentarios_usuario),
                    propuestas_mejora = VALUES(propuestas_mejora),
                    valoracion = VALUES(valoracion)
            ";
            $stmtTest = $conn->prepare($sqlTest);
            if ($stmtTest === false) {
                throw new Exception('Error en prepare (tests_usabilidad): ' . $conn->error);
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === 1 && trim((string)$row[0]) === '') {
                    $skipped++;
                    continue;
                }
                if (count($row) !== 12) {
                    $skipped++;
                    continue;
                }

                [$idTest, $idUsuario, $profesion, $edad, $genero, $pericia, $dispositivo, $duracion, $completado, $comentarios, $propuestas, $valoracion] = $row;

                // Conversión / normalización
                $idTest = ($idTest === '' ? null : (int)$idTest);
                $idUsuario = ($idUsuario === '' ? null : (int)$idUsuario);

                $profesion = ($profesion === '' ? null : $profesion);
                $edad = ($edad === '' ? null : (int)$edad);
                $genero = ($genero === '' ? null : $genero);
                $pericia = ($pericia === '' ? null : $pericia);

                $dispositivo = ($dispositivo === '' ? null : $dispositivo);
                $duracion = ($duracion === '' ? null : (int)$duracion);
                $completado = ($completado === '' ? null : (int)$completado);
                $comentarios = ($comentarios === '' ? null : $comentarios);
                $propuestas = ($propuestas === '' ? null : $propuestas);
                $valoracion = ($valoracion === '' ? null : (int)$valoracion);

                // Validación mínima
                if ($idTest === null) {
                    $skipped++;
                    continue;
                }

                // Upsert usuario solo si hay idUsuario
                if ($idUsuario !== null) {
                    $stmtUsuario->bind_param(
                        'isiss',
                        $idUsuario,
                        $profesion,
                        $edad,
                        $genero,
                        $pericia
                    );
                    if (!$stmtUsuario->execute()) {
                        throw new Exception('Error al insertar/actualizar usuario: ' . $stmtUsuario->error);
                    }
                }

                $stmtTest->bind_param(
                    'iisisssi',
                    $idTest,
                    $idUsuario,
                    $dispositivo,
                    $duracion,
                    $completado,
                    $comentarios,
                    $propuestas,
                    $valoracion
                );
                if (!$stmtTest->execute()) {
                    throw new Exception('Error al insertar/actualizar test: ' . $stmtTest->error);
                }

                // affected_rows: 1 insert, 2 update (en ON DUPLICATE KEY)
                if ($stmtTest->affected_rows === 1) {
                    $inserted++;
                } elseif ($stmtTest->affected_rows === 2) {
                    $updated++;
                }
            }

            $stmtUsuario->close();
            $stmtTest->close();
            fclose($handle);

            $conn->commit();
            $conn->close();

            return "Importación completada. Insertados: {$inserted}. Actualizados: {$updated}. Omitidos: {$skipped}.";
        } catch (Exception $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($conn instanceof mysqli) {
                $conn->rollback();
                $conn->close();
            }
            return 'Error al importar CSV: ' . $e->getMessage();
        }
    }

    public function elimiarBBDD(): string
    {
        try {
            $conn = $this->getConnection(false); 
            $dbName = $conn->real_escape_string($this->dbName);

            if (!$conn->query("DROP DATABASE IF EXISTS `$dbName`")) {
                $mensaje = 'Error al eliminar la BBDD: ' . $conn->error;
            } else {
                $mensaje = 'Base de datos eliminada correctamente.';
            }

            $conn->close();
            return $mensaje;
        } catch (Exception $e) {
            return 'Error al eliminar la BBDD: ' . $e->getMessage();
        }
    }

        public function guardarTestUsabilidad(
        ?int $idUsuario,
        ?string $dispositivo,
        ?int $duracionSegundos,
        ?bool $completado,
        ?string $comentariosUsuario,
        ?string $propuestasMejora,
        ?int $valoracion
    ): void {
        $conn = $this->getConnection();

        $sql = "
            INSERT INTO tests_usabilidad
                (id_usuario, dispositivo, duracion_segundos, completado, comentarios_usuario, propuestas_mejora, valoracion)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Error en prepare: ' . $conn->error);
        }

        // Pasamos el booleano a entero (0/1) o lo dejamos null si viene null
        $completadoInt = is_null($completado) ? null : ($completado ? 1 : 0);

        // i = int, s = string
        $stmt->bind_param(
            'isiissi',
            $idUsuario,          // i
            $dispositivo,        // s
            $duracionSegundos,   // i
            $completadoInt,      // i
            $comentariosUsuario, // s
            $propuestasMejora,   // s
            $valoracion          // i
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            $conn->close();
            throw new Exception('Error al insertar test de usabilidad: ' . $error);
        }

        $stmt->close();
        $conn->close();
    }

    public function reiniciarBBDD(): string
    {
        $rutaSql = __DIR__ . '/reiniciar_bbdd.sql';

        if (!file_exists($rutaSql)) {
            return 'No se ha encontrado el archivo SQL de reinicio (reiniciar_bbdd.sql).';
        }

        $sql = file_get_contents($rutaSql);
        if ($sql === false || trim($sql) === '') {
            return 'El archivo reiniciar_bbdd.sql está vacío o no se puede leer.';
        }

        try {
            $conn = $this->getConnection(); 

            if ($conn->multi_query($sql) === false) {
                $mensaje = 'Error al ejecutar el script SQL: ' . $conn->error;
            } else {
                do {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->more_results() && $conn->next_result());

                $mensaje = 'Base de datos reiniciada correctamente.';
            }

            $conn->close();
            return $mensaje;
        } catch (Exception $e) {
            return 'Error al reiniciar la BBDD: ' . $e->getMessage();
        }
    }
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = new Configruacion();

    if (isset($_POST['crear'])) {
        $mensaje = $config->crearBBDD();
    } elseif (isset($_POST['reiniciar'])) {
        $mensaje = $config->reiniciarBBDD();
    } elseif (isset($_POST['eliminar'])) {
        $mensaje = $config->elimiarBBDD();
    } elseif (isset($_POST['csv'])) {
        $config->guardarCSV();
    } elseif (isset($_POST['importar_csv'])) {
        if (!isset($_FILES['csv_file'])) {
            $mensaje = 'No se ha recibido ningún archivo.';
        } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $mensaje = 'Error al subir el archivo CSV (código: ' . (int)$_FILES['csv_file']['error'] . ').';
        } else {
            $mime = mime_content_type($_FILES['csv_file']['tmp_name']);
            $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                $mensaje = 'El archivo debe tener extensión .csv';
            } elseif (!in_array($mime, ['text/plain', 'text/csv', 'application/vnd.ms-excel', 'application/csv', 'application/octet-stream'], true)) {
                // Algunos servidores devuelven application/octet-stream para CSV.
                $mensaje = 'El archivo subido no parece ser un CSV válido.';
            } else {
                $mensaje = $config->importarCSV($_FILES['csv_file']['tmp_name']);
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <!-- Datos que describen el documento -->
    <meta charset="UTF-8" />
    <title>Juegos MotoGP-Desktop</title>
    <meta name ="author" content ="Alfredo Jirout Cid" />
    <meta name ="description" content ="Juego de memoria con cartas" />
    <meta name ="keywords" content ="Memoria, Juego, Mental, MotoGP" />
    <meta name ="viewport" content ="width=device-width, initial-scale=1.0" /> 
    <link rel="stylesheet" type="text/css" href="../estilo/estilo.css" />
    <link rel="stylesheet" type="text/css" href="../estilo/layout.css" />
    <link rel="icon" type="image/png" href="../multimedia/favicon.ico" />

</head>
<body>
    <header>
        <h1><a href="../index.html">MotoGP Desktop</a></h1>
    </header>

    <h2>Configuración de la BBDD</h2>

    <?php if (!empty($mensaje)): ?>
        <p class="mensaje"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <p>
            <button type="submit" name="crear">Crear BBDD</button>
            <button type="submit" name="reiniciar">Reiniciar BBDD</button>
            <button type="submit" name="eliminar">Eliminar BBDD</button>
            <button type="submit" name="csv">Exportar .csv</button>
        </p>
        <p>
            <label for="csv_file">Importar CSV:</label>
            <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" />
            <button type="submit" name="importar_csv">Importar</button>
        </p>
    </form>


</body>
</html>
