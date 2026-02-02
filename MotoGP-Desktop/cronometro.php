<?php
class Cronometro {

    private float $tiempo;
    private ?float $inicio;

    public function __construct() {
        $this->tiempo = 0.0;
        $this->inicio = null;
    }

    public function arrancar(): void {
        $this->inicio = microtime(true);
    }

    public function parar(): void {
        if ($this->inicio !== null) {
            $this->tiempo += microtime(true) - $this->inicio;
            $this->inicio = null;
        }
    }

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
}

session_start();
if (!isset($_SESSION['cronometro'])) {
    $_SESSION['cronometro'] = new Cronometro();
}

$cronometro = $_SESSION['cronometro'];
$salida = "";

if (isset($_POST['arrancar'])) {
    $cronometro->arrancar();
}

if (isset($_POST['parar'])) {
    $cronometro->parar();
}

if (isset($_POST['mostrar'])) {
    $salida = $cronometro->mostrar();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"/>
    <title>Cronómetro MotoGP-Desktop</title>
    <meta name ="author" content ="Alfredo Jirout Cid"/>
    <meta name ="description" content ="Cronometro para MotoGP"/>
    <meta name ="keywords" content ="Cronometro, Tiempo, MotoGP"/>
    <meta name ="viewport" content ="width=device-width, initial-scale=1.0"/> 
    <link rel="stylesheet" type="text/css" href="estilo/estilo.css"/>
    <link rel="stylesheet" type="text/css" href="estilo/layout.css"/>
    <link rel="icon" type="image/png" href="multimedia/favicon.ico"/>
</head>

<body>

<header>
    <h1><a href="index.html">MotoGP Desktop</a></h1>
    <button type="button"
            aria-expanded="false"
            aria-label="Abrir menú de navegación">
        ☰ Menú
    </button>
    <nav data-open="false">
        <a href="index.html">Inicio</a>
        <a href="piloto.html">Piloto</a>
        <a href="circuito.html">Circuito</a>
        <a href="meteorologia.html">Meteorología</a>
        <a href="clasificaciones.php">Clasificaciones</a>
        <a href="juegos.html">Juegos</a>
        <a href="ayuda.html">Ayuda</a>
    </nav>
</header>
    <p>Used se encuentra en: [<a href="index.html" title="inicio"> Inicio </a>] >> [Juegos] >>[Cronometro]</p>

<main>
    <h2>Cronómetro</h2>
    <article>
        <h3>Controles</h3>

        <form method="post">
            <p>
                <button type="submit" name="arrancar">Arrancar</button>
                <button type="submit" name="parar">Parar</button>
                <button type="submit" name="mostrar">Mostrar tiempo</button>
            </p>
        </form>

        <h3>Tiempo actual</h3>
        <p><?= $salida ?></p>
    </article>

</main>
<script src="js/menu.js"></script>
</body>
</html>
