<?php
session_start();

/* ============================================================
   ===============  INICIALIZAR VARIABLES NECESARIAS ===========
   ============================================================ */

$errores = "";
$resultado = "";
$mensajeComentario = "";
$mostrarFormularioComentario = false;

/* ============================================================
   ===============  CLASE DEL CRONÓMETRO =======================
   ============================================================ */
class Cronometro {

    private float $tiempo;
    private ?float $inicio;

    public function __construct() {
        $this->tiempo = 0.0;
        $this->inicio = null;
    }

    public function arrancar(): void {
        if ($this->inicio === null) {
            $this->inicio = microtime(true);
        }
    }

    public function parar(): void {
        if ($this->inicio !== null) {
            $this->tiempo += microtime(true) - $this->inicio;
            $this->inicio = null;
        }
    }

    // Formato mm:ss.d (por si lo quieres usar en algún sitio interno)
    public function mostrar(): string {
        $tiempoActual = $this->tiempo;

        if ($this->inicio !== null) {
            $tiempoActual += microtime(true) - $this->inicio;
        }

        $minutos = floor($tiempoActual / 60);
        $segundos = floor($tiempoActual % 60);
        $decimas  = floor(($tiempoActual - floor($tiempoActual)) * 10);

        return sprintf("%02d:%02d.%d", $minutos, $segundos, $decimas);
    }

    // Duración en segundos (para BBDD)
    public function getDuracionSegundos(): int {
        $tiempoActual = $this->tiempo;

        if ($this->inicio !== null) {
            $tiempoActual += microtime(true) - $this->inicio;
        }

        return (int) round($tiempoActual);
    }
}

/* ============================================================
   ===============  FUNCIÓN PARA CONECTAR CON PDO ==============
   ============================================================ */

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

/* ============================================================
   ===============  CREAR CRONÓMETRO SI NO EXISTE ==============
   ============================================================ */

if (!isset($_SESSION['cronometro'])) {
    $_SESSION['cronometro'] = new Cronometro();
}
$cronometro = $_SESSION['cronometro'];

/* ============================================================
   ===============  PROCESADO DEL FORMULARIO ===================
   ============================================================ */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* -----------------------------------------------------------
       1) Iniciar prueba
       ----------------------------------------------------------- */
    if (isset($_POST['arrancar'])) {

        // REINICIAR cronómetro para nueva prueba
        $cronometro = new Cronometro();
        $cronometro->arrancar();
        $_SESSION['cronometro'] = $cronometro;

    /* -----------------------------------------------------------
       2) Enviar comentario
       ----------------------------------------------------------- */
    } elseif (isset($_POST['enviarComentario'])) {

        $comentario = trim($_POST['comentario'] ?? "");

        // Intentamos obtener el id_test primero del POST y, si no, de la sesión
        $idTest = null;
        if (isset($_POST['id_test']) && ctype_digit($_POST['id_test'])) {
            $idTest = (int) $_POST['id_test'];
        } elseif (isset($_SESSION['id_test_actual'])) {
            $idTest = (int) $_SESSION['id_test_actual'];
        }

        if ($comentario === "") {
            $mensajeComentario = "El comentario no puede estar vacío.";
        } elseif ($idTest === null) {
            $mensajeComentario = "No se ha encontrado el test asociado. Realiza primero la prueba.";
        } else {

            try {
                $pdo = getPDO();

                $sql = "INSERT INTO observaciones_facilitador (id_test, comentarios_facilitador)
                        VALUES (:id_test, :comentarios_facilitador)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ":id_test" => $idTest,
                    ":comentarios_facilitador" => $comentario
                ]);

                $mensajeComentario = "¡Comentario enviado correctamente!";

                // OJO: ya NO eliminamos id_test_actual, para poder volver más tarde
                // unset($_SESSION['id_test_actual']);

            } catch (Throwable $e) {
                $mensajeComentario = "Error al guardar las observaciones en la BBDD: " . $e->getMessage();
            }
        }

    /* -----------------------------------------------------------
       3) Enviar TEST (corrección del cuestionario)
       ----------------------------------------------------------- */
    } else {

        $cronometro->parar();
        $duracionSegundos = $cronometro->getDuracionSegundos();

        // Comprobar que estén todas las respuestas
        for ($i = 1; $i <= 10; $i++) {
            if (!isset($_POST["pregunta$i"])) {
                $errores = "Debes responder todas las preguntas.";
                break;
            }
        }

        if ($errores === "") {

            $correctas = [
                1 => "A",
                2 => "C",
                3 => "D",
                4 => "A",
                5 => "D",
                6 => "C",
                7 => "B",
                8 => "D",
                9 => "B",
                10 => "C"
            ];

            $aciertos = 0;

            foreach ($correctas as $num => $correcta) {
                if ($_POST["pregunta$num"] === $correcta) {
                    $aciertos++;
                }
            }

            $resultado = "<p>Has acertado <strong>$aciertos / 10</strong> preguntas.</p>";

            // Guardar en tests_usabilidad SOLO:
            // duracion_segundos y completado (resto NULL)
            try {
                $pdo = getPDO();

                $sql = "INSERT INTO tests_usabilidad (duracion_segundos, completado)
                        VALUES (:duracion_segundos, :completado)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ":duracion_segundos" => $duracionSegundos,
                    ":completado"        => 1
                ]);

                $_SESSION['id_test_actual'] = $pdo->lastInsertId();

                // Opcional: resetear cronómetro tras guardar
                $_SESSION['cronometro'] = new Cronometro();

            } catch (Throwable $e) {
                $errores = "Error al guardar el test en la BBDD: " . $e->getMessage();
            }
        }
    }

    $_SESSION['cronometro'] = $cronometro;
}

/* -----------------------------------------------------------
   Mostrar siempre el formulario de comentario si hay test
   ----------------------------------------------------------- */
if (isset($_SESSION['id_test_actual'])) {
    $mostrarFormularioComentario = true;
}
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Juegos MotoGP-Desktop</title>
    <meta name="author" content="Alfredo Jirout Cid">
    <meta name="description" content="Juego de memoria con cartas">
    <meta name="keywords" content="Memoria, Juego, Mental, MotoGP">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../estilo/estilo.css">
    <link rel="stylesheet" type="text/css" href="../estilo/layout.css">
    <link rel="icon" type="image/png" href="../multimedia/favicon.ico">
</head>

<body>

<header>
    <h1><a href="../index.html">MotoGP Desktop</a></h1>
    
</header>

<h2>Cuestionario MotoGP</h2>

<form method="post">
    <button type="submit" name="arrancar">Iniciar prueba</button>
</form>

<?php
if ($resultado !== "") {
    echo $resultado;

    // Botón para ir al PHP de información adicional (si hay test)
    if (isset($_SESSION['id_test_actual'])) {
        $idTest = (int) $_SESSION['id_test_actual'];
        echo '
        <form method="get" action="añadir_info.php">
            <input type="hidden" name="id_test" value="' . $idTest . '">
            <button type="submit">Añadir información del test</button>
        </form>
        ';
    }
}

if ($errores !== "") {
    echo "<p style='color:red;'>$errores</p>";
}

if ($mensajeComentario !== "") {
    echo "<p>$mensajeComentario</p>";
}
?>

<!-- FORMULARIO DEL TEST -->
<form method="post">

    <!-- Pregunta 1 -->
    <fieldset>
        <legend>¿Cuál es el nombre del circuito?</legend>
        <label><input type="radio" name="pregunta1" value="A" required> Termas de Río Hondo</label><br>
        <label><input type="radio" name="pregunta1" value="B"> Isle of Man</label><br>
        <label><input type="radio" name="pregunta1" value="C"> Albi</label><br>
        <label><input type="radio" name="pregunta1" value="D"> MotorLand Aragón</label>
    </fieldset><br>

    <!-- Pregunta 2 -->
    <fieldset>
        <legend>¿En qué país se encuentra el circuito?</legend>
        <label><input type="radio" name="pregunta2" value="A" required> España</label><br>
        <label><input type="radio" name="pregunta2" value="B"> Isla de Navidad</label><br>
        <label><input type="radio" name="pregunta2" value="C"> Argentina</label><br>
        <label><input type="radio" name="pregunta2" value="D"> R. Centroafricana</label>
    </fieldset><br>

    <!-- Pregunta 3 -->
    <fieldset>
        <legend>¿Quién ganó la carrera?</legend>
        <label><input type="radio" name="pregunta3" value="A" required> Rafael Nadal</label><br>
        <label><input type="radio" name="pregunta3" value="B"> Franco Morbidelli</label><br>
        <label><input type="radio" name="pregunta3" value="C"> Álex Márquez</label><br>
        <label><input type="radio" name="pregunta3" value="D"> Marc Márquez</label>
    </fieldset><br>

    <!-- Pregunta 4 -->
    <fieldset>
        <legend>¿Cuántas vueltas se dan al circuito?</legend>
        <label><input type="radio" name="pregunta4" value="A" required> 25</label><br>
        <label><input type="radio" name="pregunta4" value="B"> 33</label><br>
        <label><input type="radio" name="pregunta4" value="C"> 5</label><br>
        <label><input type="radio" name="pregunta4" value="D"> 100</label>
    </fieldset><br>

    <!-- Pregunta 5 -->
    <fieldset>
        <legend>¿Cuál es la longitud del circuito?</legend>
        <label><input type="radio" name="pregunta5" value="A" required> 3182462mm</label><br>
        <label><input type="radio" name="pregunta5" value="B"> 4312km</label><br>
        <label><input type="radio" name="pregunta5" value="C"> 3124m</label><br>
        <label><input type="radio" name="pregunta5" value="D"> 4806m</label>
    </fieldset><br>

    <!-- Pregunta 6 -->
    <fieldset>
        <legend>¿Qué afirmación es FALSA sobre Marco Bezzecchi?</legend>
        <label><input type="radio" name="pregunta6" value="A" required> Nacido en Rimini</label><br>
        <label><input type="radio" name="pregunta6" value="B"> Su dorsal es el 72</label><br>
        <label><input type="radio" name="pregunta6" value="C"> Es ultra de la Lazio</label><br>
        <label><input type="radio" name="pregunta6" value="D"> Es italiano</label>
    </fieldset><br>

    <!-- Pregunta 7 -->
    <fieldset>
        <legend>¿Cuál de estas afirmaciones es VERDADERA sobre Bezzecchi?</legend>
        <label><input type="radio" name="pregunta7" value="A" required> Compitió para RangersGP</label><br>
        <label><input type="radio" name="pregunta7" value="B"> Compitió para Minimoto Portomaggiore</label><br>
        <label><input type="radio" name="pregunta7" value="C"> Compitió para Ducati</label><br>
        <label><input type="radio" name="pregunta7" value="D"> Compitió para KTM</label>
    </fieldset><br>

    <!-- Pregunta 8 -->
    <fieldset>
        <legend>¿Quién quedó tercero en Termas de Río Hondo?</legend>
        <label><input type="radio" name="pregunta8" value="A" required> Cristiano Ronaldo</label><br>
        <label><input type="radio" name="pregunta8" value="B"> Marc Márquez</label><br>
        <label><input type="radio" name="pregunta8" value="C"> Álex Márquez</label><br>
        <label><input type="radio" name="pregunta8" value="D"> Franco Morbidelli</label>
    </fieldset><br>

    <!-- Pregunta 9 -->
    <fieldset>
        <legend>¿Qué tiempo se empleó en acabar la carrera?</legend>
        <label><input type="radio" name="pregunta9" value="A" required> 41:14.1</label><br>
        <label><input type="radio" name="pregunta9" value="B"> 41:11.1</label><br>
        <label><input type="radio" name="pregunta9" value="C"> 41:11.4</label><br>
        <label><input type="radio" name="pregunta9" value="D"> 44:11.1</label>
    </fieldset><br>

    <!-- Pregunta 10 -->
    <fieldset>
        <legend>¿Quién quedó segundo?</legend>
        <label><input type="radio" name="pregunta10" value="A" required> Fernando Alonso</label><br>
        <label><input type="radio" name="pregunta10" value="B"> Marc Márquez</label><br>
        <label><input type="radio" name="pregunta10" value="C"> Álex Márquez</label><br>
        <label><input type="radio" name="pregunta10" value="D"> Franco Morbidelli</label>
    </fieldset><br>

    <button type="submit">Enviar test</button>
</form>

<?php if ($mostrarFormularioComentario && isset($_SESSION['id_test_actual'])): ?>
    <hr>
    <h3>¿Quieres comentar la prueba?</h3>
    <form method="post">
        <!-- Enlazamos el comentario con el test mediante un campo oculto -->
        <input type="hidden" name="id_test" value="<?php echo (int)$_SESSION['id_test_actual']; ?>">
        <textarea name="comentario" rows="5" cols="60" placeholder="Escribe aquí tu comentario sobre la prueba..."></textarea><br><br>
        <button type="submit" name="enviarComentario">Enviar comentario</button>
    </form>
<?php endif; ?>

</body>
</html>
