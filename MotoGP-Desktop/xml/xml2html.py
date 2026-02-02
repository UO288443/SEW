# -*- coding: utf-8 -*-
import xml.etree.ElementTree as ET
from html import escape
from pathlib import Path


def generar_info_circuito(
    xml_path="circuitoEsquema.xml",
    css_estilo="../estilo/estilo.css",
    css_layout="../estilo/layout.css",
    favicon="../multimedia/favicon.ico",
    autor="MotoGP-Desktop",
    descripcion="Ficha del circuito generada desde circuitoEsquema.xml",
    keywords="MotoGP, circuito, MotoGP-Desktop",
):
    base_dir = Path(__file__).parent
    xml_path = base_dir / xml_path
    salida_html = base_dir / "InfoCircuito.html"

    tree = ET.parse(xml_path)
    root = tree.getroot()
    ns = "{https://www.uniovi.es/}"

    def txt(tag):
        el = root.find(f"{ns}{tag}")
        return el.text.strip() if el is not None and el.text else ""

    def child(parent, tag):
        el = parent.find(f"{ns}{tag}") if parent is not None else None
        return el.text.strip() if el is not None and el.text else ""

    def norm_media(src: str) -> str:
        if not src:
            return ""
        return src.lstrip("./")

    nombre = txt("nombre_circuito") or "Circuito"
    pais = txt("pais")
    localidad = txt("localidad_proxima")
    patrocinador = txt("patrocinador")
    fecha = txt("fecha_carrera_2025")
    hora = txt("hora_carrera_españa")
    vueltas = txt("numero_vueltas")

    longitud_el = root.find(f"{ns}longitud_circuito")
    longitud = (longitud_el.text or "").strip() if longitud_el is not None else ""
    longitud_u = longitud_el.get("unidades") if longitud_el is not None else ""

    anchura_el = root.find(f"{ns}anchura")
    anchura = (anchura_el.text or "").strip() if anchura_el is not None else ""
    anchura_u = anchura_el.get("unidades") if anchura_el is not None else ""

    coords = root.find(f"{ns}origen_circuito/{ns}coordenadas")
    lon = child(coords, "longitud")
    lat = child(coords, "latitud")
    alt = child(coords, "altitud")

    fotos = [
        f.text.strip()
        for f in root.findall(f"{ns}galeria_fotos/{ns}foto")
        if f.text and f.text.strip()
    ]
    videos = [
        v.text.strip()
        for v in root.findall(f"{ns}galeria_videos/{ns}video")
        if v.text and v.text.strip()
    ]
    refs = [
        r.text.strip()
        for r in root.findall(f"{ns}referencias_bibliograficas/{ns}referencia")
        if r.text and r.text.strip()
    ]

    vencedor = root.find(f"{ns}vencedor")
    vencedor_nombre = child(vencedor, "nombre")
    vencedor_tiempo = child(vencedor, "tiempo")
    podio = [
        (p.get("posicion", ""), p.text.strip())
        for p in root.findall(f"{ns}podio_2025/{ns}nombre_piloto")
        if p.text and p.text.strip()
    ]

    fotos_html = (
        '<section class="galeria-fotos">\n'
        '  <h3>Galería de fotos</h3>\n'
        '  <div class="fotos">\n'
        + "".join(
            f'    <figure>'
            f'<img src="{escape(norm_media(src))}" alt="Foto del circuito {escape(nombre)}" '
            f'title="{escape(nombre)}" loading="lazy">'
            f'<figcaption>{escape(Path(src).name)}</figcaption>'
            f"</figure>\n"
            for src in fotos
        )
        + ('    <p>No hay imágenes disponibles.</p>\n' if not fotos else "")
        + "  </div>\n"
        "</section>\n"
    )

    videos_html = (
        '<section class="galeria-videos">\n'
        "  <h3>Galería de vídeos</h3>\n"
        '  <div class="videos">\n'
        + "".join(
            f"    <figure>"
            f'<video controls preload="metadata" aria-label="Vídeo {i+1} del circuito {escape(nombre)}">'
            f'<source src="{escape(norm_media(v))}" type="video/mp4">'
            f"</video></figure>\n"
            for i, v in enumerate(videos)
        )
        + ('    <p>No hay vídeos disponibles.</p>\n' if not videos else "")
        + "  </div>\n"
        "</section>\n"
    )

    refs_html = (
        "<br>".join(f'<a href="{escape(r)}">{escape(r)}</a>' for r in refs)
        or "<p>Sin referencias.</p>"
    )

    podio_html = "".join(
        f"<li>#{escape(pos)} {escape(nombre_p)}</li>" for pos, nombre_p in podio
    )

    html = f"""<!DOCTYPE HTML>

<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Circuito MotoGP-Desktop</title>
    <meta name="author" content="{escape(autor)}" />
    <meta name="description" content="{escape(descripcion)}" />
    <meta name="keywords" content="{escape(keywords)}" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" /> 
    <link rel="stylesheet" type="text/css" href="{escape(css_estilo)}" />
    <link rel="stylesheet" type="text/css" href="{escape(css_layout)}" />
    <link rel="icon" type="image/x-icon" href="{escape(favicon)}" />
</head>

<body>
    <header>
        <h1><a href="../index.html">MotoGP Desktop</a></h1>
        <nav>
            <a href="../index.html">Inicio</a>  
            <a href="../piloto.html">Piloto</a>  
            <a class="active" href="../circuito.html">Circuito</a> 
            <a href="../meteorología.html">Meteorología</a>
            <a href="../clasificaciones.html">Clasificaciones</a>
            <a href="../juegos.html">Juegos</a>
            <a href="../ayuda.html">Ayuda</a>
        </nav>
    </header>

    <p>Usted se encuentra en: [<a href="../index.html" title="inicio"> Inicio </a>] >> <strong>[Circuito]</strong></p>

    <main>

        <section>
            <h2>Perfil del circuito: {escape(nombre)}</h2>
            <p>
                <strong>País:</strong> {escape(pais)} · 
                <strong>Localidad próxima:</strong> {escape(localidad)} · 
                <strong>Patrocinador:</strong> {escape(patrocinador)} · 
                <strong>Fecha:</strong> {escape(fecha)} · 
                <strong>Hora (España):</strong> {escape(hora)}
            </p>

            <h3>Datos técnicos</h3>
            <ul>
                <li>Longitud del circuito: {escape(longitud)} {escape(longitud_u)}</li>
                <li>Anchura del circuito: {escape(anchura)} {escape(anchura_u)}</li>
                <li>Número de vueltas: {escape(vueltas)}</li>
                <li>Coordenadas: Lat {escape(lat)}, Lon {escape(lon)}, Alt {escape(alt)} m</li>
            </ul>
        </section>

        {fotos_html}
        {videos_html}

        <aside>
            <h3>Resultados recientes</h3>
            <dl>
                <dt>Vencedor</dt>
                <dd>{escape(vencedor_nombre)} ({escape(vencedor_tiempo)})</dd>
                <dt>Podio 2025</dt>
                <dd>
                    <ol>
                        {podio_html}
                    </ol>
                </dd>
            </dl>
        </aside>
        
        <article>
            <h3>Ficha resumen</h3>
            <table>
                <caption>Resumen del circuito</caption>
                <tr>
                    <th scope="col" id="item">Dato</th>
                    <th scope="col" id="valor">Valor</th>
                </tr>
                <tr>
                    <td headers="item">Nombre</td>
                    <td headers="valor">{escape(nombre)}</td>
                </tr>
                <tr>
                    <td headers="item">Ubicación</td>
                    <td headers="valor">{escape(localidad)} ({escape(pais)})</td>
                </tr>
                <tr>
                    <td headers="item">Longitud</td>
                    <td headers="valor">{escape(longitud)} {escape(longitud_u)}</td>
                </tr>
                <tr>
                    <td headers="item">Anchura</td>
                    <td headers="valor">{escape(anchura)} {escape(anchura_u)}</td>
                </tr>
                <tr>
                    <td headers="item">Vueltas</td>
                    <td headers="valor">{escape(vueltas)}</td>
                </tr>
            </table>
        </article>
    </main>
    
    <footer>
        <p>Documento generado automáticamente desde <code>circuitoEsquema.xml</code>.</p>
        <h4>Referencias</h4>
        <br>
        {refs_html}
    </footer>
</body>
</html>"""

    salida_html.write_text(html, encoding="utf-8")
    print(f"Archivo generado correctamente: {salida_html.resolve()}")


if __name__ == "__main__":
    generar_info_circuito()
