<?php
session_start();

/* ========= CONFIGURACIÓN PDO ========= */
function getPDO(): PDO {
    return new PDO(
        "mysql:host=localhost;dbname=uo288443_db;charset=utf8mb4",
        "DBUSER2025",
        "DBPSWD2025",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
}

$errores = [];
$mensaje = "";
$test = null;
$usuarios = [];

/* ========= OBTENER id_test ========= */
$idTest = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id_test']) && ctype_digit($_GET['id_test'])) {
        $idTest = (int) $_GET['id_test'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id_test']) && ctype_digit($_POST['id_test'])) {
        $idTest = (int) $_POST['id_test'];
    }
}

if ($idTest === null) {
    $errores[] = "No se ha indicado un test válido.";
} else {
    try {
        $pdo = getPDO();

        // Cargar datos del test
        $stmt = $pdo->prepare("SELECT * FROM tests_usabilidad WHERE id_test = :id_test");
        $stmt->execute([":id_test" => $idTest]);
        $test = $stmt->fetch();

        if (!$test) {
            $errores[] = "No se ha encontrado el test con id $idTest.";
        } else {
            // Cargar lista de usuarios para el combo (ahora también nombre)
            $stmt = $pdo->query("SELECT id_usuario, nombre, profesion, edad, genero, pericia_informatica FROM usuarios ORDER BY id_usuario ASC");
            $usuarios = $stmt->fetchAll();
        }

        /* ========= PROCESAR FORMULARIO (POST) ========= */
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $test) {

            // --- datos comunes del test ---
            $dispositivo = $_POST['dispositivo'] ?? '';
            $comentariosUsuario = trim($_POST['comentarios_usuario'] ?? "");
            $propuestasMejora   = trim($_POST['propuestas_mejora'] ?? "");
            $valoracion         = $_POST['valoracion'] ?? null;

            // Validar dispositivo (ENUM)
            $dispositivosValidos = ['Ordenador', 'Tableta', 'Teléfono'];
            if (!in_array($dispositivo, $dispositivosValidos, true)) {
                $errores[] = "Debes seleccionar un dispositivo válido.";
            }

            // Validar valoración (0–10, puede ser NULL)
            if ($valoracion !== null && $valoracion !== '') {
                if (!ctype_digit((string)$valoracion)) {
                    $errores[] = "La valoración debe ser un número entre 0 y 10.";
                } else {
                    $valoracion = (int)$valoracion;
                    if ($valoracion < 0 || $valoracion > 10) {
                        $errores[] = "La valoración debe estar entre 0 y 10.";
                    }
                }
            } else {
                $valoracion = null;
            }

            // --- gestión de usuario (existente / nuevo) ---
            $modoUsuario = $_POST['modo_usuario'] ?? 'existente';
            $idUsuarioFinal = null;

            if ($modoUsuario === 'existente') {
                // Usuario de la BBDD
                $idUsuarioSel = $_POST['id_usuario_existente'] ?? '';
                if (!ctype_digit($idUsuarioSel)) {
                    $errores[] = "Debes seleccionar un usuario existente o elegir la opción de nuevo usuario.";
                } else {
                    $idUsuarioFinal = (int)$idUsuarioSel;
                }

            } elseif ($modoUsuario === 'nuevo') {
                // Crear un usuario nuevo
                $nombre    = trim($_POST['nombre'] ?? "");
                $profesion = trim($_POST['profesion'] ?? "");
                $edad      = $_POST['edad'] ?? "";
                $genero    = $_POST['genero'] ?? "";
                $pericia   = $_POST['pericia_informatica'] ?? "";

                if ($nombre === "") {
                    $errores[] = "El nombre del nuevo usuario no puede estar vacío.";
                }

                if ($profesion === "") {
                    $errores[] = "La profesión del nuevo usuario no puede estar vacía.";
                }

                if ($edad === "" || !ctype_digit((string)$edad) || (int)$edad < 0 || (int)$edad > 120) {
                    $errores[] = "La edad del nuevo usuario debe ser un número entre 0 y 120.";
                } else {
                    $edad = (int)$edad;
                }

                $generosValidos = ['Masculino', 'Femenino'];
                if (!in_array($genero, $generosValidos, true)) {
                    $errores[] = "El género del nuevo usuario no es válido.";
                }

                $periciasValidas = ['Baja', 'Media', 'Alta'];
                if (!in_array($pericia, $periciasValidas, true)) {
                    $errores[] = "La pericia informática del nuevo usuario no es válida.";
                }

                // Si todo ok, insertamos el usuario
                if (empty($errores)) {
                    $sqlUsuario = "INSERT INTO usuarios (nombre, profesion, edad, genero, pericia_informatica)
                                   VALUES (:nombre, :profesion, :edad, :genero, :pericia)";
                    $stmtU = $pdo->prepare($sqlUsuario);
                    $stmtU->execute([
                        ":nombre"    => $nombre,
                        ":profesion" => $profesion,
                        ":edad"      => $edad,
                        ":genero"    => $genero,
                        ":pericia"   => $pericia
                    ]);

                    $idUsuarioFinal = (int)$pdo->lastInsertId();
                }

            } else {
                $errores[] = "Modo de usuario no válido.";
            }

            // Si no hay errores, actualizamos tests_usabilidad
            if (empty($errores) && $idUsuarioFinal !== null) {
                $sqlTest = "UPDATE tests_usabilidad
                            SET id_usuario = :id_usuario,
                                dispositivo = :dispositivo,
                                comentarios_usuario = :comentarios_usuario,
                                propuestas_mejora = :propuestas_mejora,
                                valoracion = :valoracion
                            WHERE id_test = :id_test";

                $stmtT = $pdo->prepare($sqlTest);
                $stmtT->execute([
                    ":id_usuario"          => $idUsuarioFinal,
                    ":dispositivo"         => $dispositivo,
                    ":comentarios_usuario" => ($comentariosUsuario !== "" ? $comentariosUsuario : null),
                    ":propuestas_mejora"   => ($propuestasMejora !== "" ? $propuestasMejora : null),
                    ":valoracion"          => $valoracion,
                    ":id_test"             => $idTest
                ]);

                $mensaje = "Información del test actualizada correctamente.";

                // Volver a cargar datos del test actualizados
                $stmt = $pdo->prepare("SELECT * FROM tests_usabilidad WHERE id_test = :id_test");
                $stmt->execute([":id_test" => $idTest]);
                $test = $stmt->fetch();

                // Volver a cargar usuarios (por si acabamos de crear uno nuevo)
                $stmt = $pdo->query("SELECT id_usuario, nombre, profesion, edad, genero, pericia_informatica FROM usuarios ORDER BY id_usuario ASC");
                $usuarios = $stmt->fetchAll();
            }
        }

    } catch (Throwable $e) {
        $errores[] = "Error al acceder a la BBDD: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Información adicional del test</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../estilo/estilo.css">
    <link rel="stylesheet" type="text/css" href="../estilo/layout.css">
    <script>
        function actualizarVisibilidadUsuario() {
            var modoExistente = document.querySelector('input[name="modo_usuario"][value="existente"]');
            var bloqueExistente = document.querySelector('[data-bloque-usuario="existente"]');
            var bloqueNuevo = document.querySelector('[data-bloque-usuario="nuevo"]');

            if (!modoExistente || !bloqueExistente || !bloqueNuevo) {
                return;
            }

            if (modoExistente.checked) {
                bloqueExistente.removeAttribute('hidden');
                bloqueNuevo.setAttribute('hidden', 'hidden');
            } else {
                bloqueExistente.setAttribute('hidden', 'hidden');
                bloqueNuevo.removeAttribute('hidden');
            }
        }
        window.addEventListener('DOMContentLoaded', actualizarVisibilidadUsuario);
    </script>
</head>
<body>

<header>
    <h1><a href="../index.html">MotoGP Desktop</a></h1>
</header>

<h2>Información adicional del test #<?php echo htmlspecialchars((string)$idTest); ?></h2>

<?php if (!empty($errores)): ?>
    <ul>
        <?php foreach ($errores as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($mensaje !== ""): ?>
    <p><?php echo htmlspecialchars($mensaje); ?></p>
<?php endif; ?>

<?php if ($test): ?>
    <form method="post">
        <input type="hidden" name="id_test" value="<?php echo (int)$idTest; ?>">

        <fieldset>
            <legend>Usuario</legend>

            <label>
                <input type="radio" name="modo_usuario" value="existente"
                       onclick="actualizarVisibilidadUsuario()" <?php
                           echo (!isset($_POST['modo_usuario']) || ($_POST['modo_usuario'] ?? '') === 'existente') ? 'checked' : '';
                       ?>>
                Seleccionar usuario existente
            </label>
            <br>
            <label>
                <input type="radio" name="modo_usuario" value="nuevo"
                       onclick="actualizarVisibilidadUsuario()" <?php
                           echo (($_POST['modo_usuario'] ?? '') === 'nuevo') ? 'checked' : '';
                       ?>>
                Añadir nuevo usuario
            </label>

            <section data-bloque-usuario="existente" hidden="hidden">
                <h3>Usuario existente</h3>
                <p>Usuario existente:
                    <select name="id_usuario_existente">
                        <option value="">-- Selecciona un usuario --</option>
                        <?php foreach ($usuarios as $u): ?>
                            <?php
                                $texto = $u['nombre'] . " - " . $u['profesion'] .
                                         " (" . $u['edad'] . " años, " .
                                         $u['genero'] . ", " .
                                         $u['pericia_informatica'] . ")";
                                $sel = "";
                                if ($test['id_usuario'] == $u['id_usuario']) {
                                    $sel = "selected";
                                }
                            ?>
                            <option value="<?php echo (int)$u['id_usuario']; ?>" <?php echo $sel; ?>>
                                <?php echo htmlspecialchars($texto); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
            </section>

            <section data-bloque-usuario="nuevo">
                <h3>Nuevo usuario</h3>

                <p>
                    Nombre:<br>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ""); ?>">
                </p>

                <p>
                    Profesión:<br>
                    <input type="text" name="profesion" value="<?php echo htmlspecialchars($_POST['profesion'] ?? ""); ?>">
                </p>

                <p>
                    Edad:<br>
                    <input type="number" name="edad" min="0" max="120"
                           value="<?php echo htmlspecialchars($_POST['edad'] ?? ""); ?>">
                </p>

                <p>
                    Género:<br>
                    <select name="genero">
                        <option value="">-- Selecciona --</option>
                        <option value="Masculino" <?php echo (($_POST['genero'] ?? '') === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                        <option value="Femenino"  <?php echo (($_POST['genero'] ?? '') === 'Femenino')  ? 'selected' : ''; ?>>Femenino</option>
                    </select>
                </p>

                <p>
                    Pericia informática:<br>
                    <select name="pericia_informatica">
                        <option value="">-- Selecciona --</option>
                        <option value="Baja"  <?php echo (($_POST['pericia_informatica'] ?? '') === 'Baja')  ? 'selected' : ''; ?>>Baja</option>
                        <option value="Media" <?php echo (($_POST['pericia_informatica'] ?? '') === 'Media') ? 'selected' : ''; ?>>Media</option>
                        <option value="Alta"  <?php echo (($_POST['pericia_informatica'] ?? '') === 'Alta')  ? 'selected' : ''; ?>>Alta</option>
                    </select>
                </p>
            </section>
        </fieldset>

        <fieldset>
            <legend>Dispositivo</legend>
            <label>Dispositivo utilizado:
                <select name="dispositivo">
                    <?php
                    $dispActual = $test['dispositivo'] ?? '';
                    $dispPost   = $_POST['dispositivo'] ?? '';
                    $valorDisp  = $dispPost !== '' ? $dispPost : $dispActual;
                    ?>
                    <option value="">-- Selecciona un dispositivo --</option>
                    <option value="Ordenador" <?php echo ($valorDisp === 'Ordenador') ? 'selected' : ''; ?>>Ordenador</option>
                    <option value="Tableta"   <?php echo ($valorDisp === 'Tableta')   ? 'selected' : ''; ?>>Tableta</option>
                    <option value="Teléfono"  <?php echo ($valorDisp === 'Teléfono')  ? 'selected' : ''; ?>>Teléfono</option>
                </select>
            </label>
        </fieldset>

        <fieldset>
            <legend>Opinión del usuario</legend>
            <p>
                Comentarios del usuario:<br>
                <textarea name="comentarios_usuario" rows="4" cols="60"><?php
                    echo htmlspecialchars($_POST['comentarios_usuario'] ?? ($test['comentarios_usuario'] ?? ""));
                ?></textarea>
            </p>

            <p>
                Propuestas de mejora:<br>
                <textarea name="propuestas_mejora" rows="4" cols="60"><?php
                    echo htmlspecialchars($_POST['propuestas_mejora'] ?? ($test['propuestas_mejora'] ?? ""));
                ?></textarea>
            </p>

            <p>
                Valoración (0–10):<br>
                <input type="number" name="valoracion" min="0" max="10"
                       value="<?php
                           $valPost = $_POST['valoracion'] ?? '';
                           $valBD   = $test['valoracion'] ?? '';
                           echo htmlspecialchars($valPost !== '' ? $valPost : $valBD);
                       ?>">
            </p>
        </fieldset>

        <p>
            <button type="submit">Guardar información</button>
        </p>
    </form>
<?php endif; ?>

<p><a href="cuestionario.php">Volver al cuestionario</a></p>

</body>
</html>
