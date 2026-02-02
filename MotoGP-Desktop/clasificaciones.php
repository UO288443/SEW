<?php 
class Clasificacion {
    private string $documento = "xml/circuitoEsquema.xml";
    private ?SimpleXMLElement $xml = null;

    public function __construct() {
        $this->cargarXML();
    }

    private function cargarXML() : void {
        $datos = @file_get_contents($this->documento);
        if ($datos === false) return;

        try {
            $xml = new SimpleXMLElement($datos);
            $xml->registerXPathNamespace('u', 'https://www.uniovi.es/');
            $this->xml = $xml;
        } catch (Exception $e) {}
    }

    public function hayDatos() : bool {
        return $this->xml !== null;
    }

    public function obtenerGanador() : ?array {
        if (!$this->hayDatos()) return null;

        $n = $this->xml->xpath('//u:vencedor/u:nombre');
        $t = $this->xml->xpath('//u:vencedor/u:tiempo');
        if (!$n || !$t) return null;

        return [
            'nombre' => (string)$n[0],
            'tiempo_iso' => (string)$t[0],
            'tiempo_legible' => $this->formatearTiempo((string)$t[0])
        ];
    }

    public function obtenerPodio() : array {
        if (!$this->hayDatos()) return [];

        $pilotos = $this->xml->xpath('//u:podio_2025/u:nombre_piloto');
        $out = [];
        foreach ($pilotos ?: [] as $p) {
            $out[] = (string)$p;
        }
        return $out;
    }

    private function formatearTiempo(string $d) : string {
        if (preg_match('/^PT(\d+)M(\d+(?:\.\d+)?)S$/', $d, $m)) {
            return sprintf('%d:%04.1f', $m[1], $m[2]);
        }
        return $d;
    }
}

$clasificacion = new Clasificacion();
?>

<!DOCTYPE HTML>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Clasificación MotoGP-Desktop</title>
    <meta name="author" content="Alfredo Jirout Cid" />
    <meta name="description" content="Página de clasificaciones de MotoGP-Desktop" />
    <meta name="keywords" content="MotoGP, MotoGP-Desktop, motos, carreras, clasificaciones" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" /> 
    <link rel="stylesheet" href="estilo/estilo.css" />
    <link rel="stylesheet" href="estilo/layout.css" />
    <link rel="icon" href="multimedia/favicon.ico" />
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
        <a href="meteorología.html">Meteorología</a>
        <a class="active" href="clasificaciones.php">Clasificaciones</a>
        <a href="juegos.html">Juegos</a>
        <a href="ayuda.html">Ayuda</a>
    </nav>
</header>

<p>Usted se encuentra en: [<a href="index.html">Inicio</a>] >> [Clasificaciones]</p>

<main>
    <h2>Clasificación</h2>

    <?php if (!$clasificacion->hayDatos()): ?>
        <section>
            <h3>Información no disponible</h3>
            <p>Error al leer el XML.</p>
        </section>

    <?php else: ?>

        <?php $ganador = $clasificacion->obtenerGanador(); ?>
        <section>
            <h3>Ganador de la carrera</h3>

            <?php if ($ganador): ?>
                <p><strong><?= htmlspecialchars($ganador['nombre']) ?></strong></p>
                <p>
                    Tiempo empleado: 
                    <time datetime="<?= htmlspecialchars($ganador['tiempo_iso']) ?>">
                        <?= htmlspecialchars($ganador['tiempo_legible']) ?>
                    </time>
                </p>
            <?php else: ?>
                <p>No hay datos del vencedor.</p>
            <?php endif; ?>
        </section>

        <?php $podio = $clasificacion->obtenerPodio(); ?>
        <section>
            <h3>Clasificación del mundial tras la carrera</h3>

            <?php if ($podio): ?>
                <ol>
                    <?php foreach ($podio as $p): ?>
                        <li><?= htmlspecialchars($p) ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p>No hay información del podio.</p>
            <?php endif; ?>
        </section>

    <?php endif; ?>
</main>
<script src="js/menu.js"></script>
</body>
</html>
