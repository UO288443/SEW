# -*- coding: utf-8 -*-
"""
xml2altimetria.py
Genera un SVG de altimetría a partir de circuitoEsquema.xml (mismo directorio).
"""

import xml.etree.ElementTree as ET
from pathlib import Path
import math
import re
import sys

SVG_WIDTH = 1200
SVG_HEIGHT = 450
MARGIN_LEFT = 80
MARGIN_RIGHT = 30
MARGIN_TOP = 40
MARGIN_BOTTOM = 70

AXIS_COLOR = "#333"
GRID_COLOR = "#ddd"
PROFILE_STROKE = "#8B0000"
PROFILE_STROKE_WIDTH = "2"


def parse_float(texto, default=0.0):
    if texto is None:
        return default
    s = str(texto).strip().replace("\u00A0", " ")
    if "," in s and "." in s:
        s = s.replace(".", "").replace(",", ".")
    else:
        s = s.replace(",", ".")
    s = re.sub(r"[^\d\.\-\+eE]", "", s)
    try:
        return float(s)
    except Exception:
        return default


def generar_altimetria(xml_path: Path):
    print(f"\nLeyendo archivo XML: {xml_path}")
    if not xml_path.exists():
        print("ERROR: No se encontró el archivo XML.")
        sys.exit(1)

    try:
        tree = ET.parse(xml_path)
        root = tree.getroot()
    except Exception as e:
        print(f"Error al leer el XML: {e}")
        sys.exit(1)

    ns = "{https://www.uniovi.es/}"

    puntos_nodes = root.findall(f".//{ns}puntos/{ns}punto")
    if not puntos_nodes:
        print("No se encontraron nodos <punto> en el XML.")
        sys.exit(1)

    dist_raw, altitudes = [], []
    for p in puntos_nodes:
        d_el = p.find(f"{ns}distancia")
        alt_el = p.find(f"{ns}coordenadas/{ns}altitud")
        if alt_el is None:
            alt_el = p.find(f"{ns}altitud")
        d_text = d_el.text if d_el is not None and d_el.text is not None else "0"
        alt_text = alt_el.text if alt_el is not None and alt_el.text is not None else "0"
        dist_raw.append(parse_float(d_text, 0.0))
        altitudes.append(parse_float(alt_text, 0.0))

    if len(altitudes) < 2:
        print("Muy pocos puntos de altitud para dibujar.")
        sys.exit(1)

    sum_increments = sum(max(0.0, dist_raw[i] - dist_raw[i - 1]) for i in range(1, len(dist_raw)))
    is_cumulative = dist_raw[-1] > 0 and abs(sum_increments - dist_raw[-1]) / dist_raw[-1] <= 0.05

    if is_cumulative:
        x_acum = dist_raw[:]
    else:
        x_acum, acc = [], 0.0
        for tr in dist_raw:
            acc += max(0.0, tr)
            x_acum.append(acc)

    total_dist = x_acum[-1]
    min_alt = min(altitudes)
    max_alt = max(altitudes)
    alt_range = max_alt - min_alt

    print(f"Puntos: {len(altitudes)}  |  Distancia total: {total_dist:.2f} m")
    print(f"Altitud min: {min_alt:.2f} m | max: {max_alt:.2f} m")

    if alt_range == 0:
        pad = max(1.0, abs(min_alt) * 0.01)
        min_alt -= pad
        max_alt += pad
        alt_range = max_alt - min_alt

    plot_x0 = MARGIN_LEFT
    plot_x1 = SVG_WIDTH - MARGIN_RIGHT
    plot_y0 = MARGIN_TOP
    plot_y1 = SVG_HEIGHT - MARGIN_BOTTOM
    plot_width = plot_x1 - plot_x0
    plot_height = plot_y1 - plot_y0

    def scale_x(d):
        return plot_x0 + (d / total_dist) * plot_width if total_dist else plot_x0

    def scale_y(a):
        return plot_y0 + (1 - (a - min_alt) / alt_range) * plot_height

    base_y = plot_y1
    end_x = scale_x(total_dist)

    poly_points = " ".join(f"{scale_x(x):.2f},{scale_y(a):.2f}" for x, a in zip(x_acum, altitudes))
    poly_points = f"{poly_points} {end_x:.2f},{base_y:.2f}"

    svg = ET.Element(
        "svg",
        {
            "xmlns": "http://www.w3.org/2000/svg",
            "version": "1.1",
            "width": str(SVG_WIDTH),
            "height": str(SVG_HEIGHT),
            "viewBox": f"0 0 {SVG_WIDTH} {SVG_HEIGHT}",
        },
    )

    ET.SubElement(
        svg,
        "rect",
        {"x": "0", "y": "0", "width": str(SVG_WIDTH), "height": str(SVG_HEIGHT), "fill": "white"},
    )

    nombre = root.find(f".//{ns}nombre_circuito")
    titulo = f"Altimetría - {nombre.text}" if nombre is not None and nombre.text else "Altimetría del circuito"
    ET.SubElement(
        svg,
        "text",
        {
            "x": f"{SVG_WIDTH/2:.2f}",
            "y": "24",
            "font-size": "18",
            "text-anchor": "middle",
            "font-weight": "bold",
            "fill": AXIS_COLOR,
        },
    ).text = titulo

    ET.SubElement(
        svg,
        "line",
        {"x1": str(plot_x0), "y1": str(plot_y1), "x2": str(plot_x1), "y2": str(plot_y1), "stroke": AXIS_COLOR, "stroke-width": "1.5"},
    )
    ET.SubElement(
        svg,
        "line",
        {"x1": str(plot_x0), "y1": str(plot_y0), "x2": str(plot_x0), "y2": str(plot_y1), "stroke": AXIS_COLOR, "stroke-width": "1.5"},
    )

    tick_x_step = 500.0 if total_dist > 2500 else 200.0
    n_ticks_x = int(math.floor(total_dist / tick_x_step)) + 1
    for i in range(n_ticks_x + 1):
        d = min(i * tick_x_step, total_dist)
        xx = scale_x(d)
        ET.SubElement(
            svg,
            "line",
            {
                "x1": f"{xx:.2f}",
                "y1": f"{plot_y0}",
                "x2": f"{xx:.2f}",
                "y2": f"{plot_y1}",
                "stroke": GRID_COLOR,
                "stroke-width": "1",
                "stroke-dasharray": "3 4",
            },
        )
        ET.SubElement(
            svg,
            "line",
            {
                "x1": f"{xx:.2f}",
                "y1": f"{plot_y1}",
                "x2": f"{xx:.2f}",
                "y2": f"{plot_y1+6}",
                "stroke": AXIS_COLOR,
                "stroke-width": "1",
            },
        )
        t = ET.SubElement(
            svg,
            "text",
            {
                "x": f"{xx:.2f}",
                "y": f"{plot_y1+22}",
                "font-size": "12",
                "text-anchor": "middle",
                "fill": AXIS_COLOR,
            },
        )
        t.text = f"{int(round(d))} m"

    def nice_step(span):
        for c in [1, 2, 5, 10, 20, 25, 50, 100]:
            if 4 <= span / c <= 10:
                return c
        return max(1, round(span / 6))

    y_step = nice_step(max_alt - min_alt)
    y_val = math.floor(min_alt / y_step) * y_step
    while y_val <= max_alt + 1e-9:
        yy = scale_y(y_val)
        ET.SubElement(
            svg,
            "line",
            {
                "x1": f"{plot_x0}",
                "y1": f"{yy:.2f}",
                "x2": f"{plot_x1}",
                "y2": f"{yy:.2f}",
                "stroke": GRID_COLOR,
                "stroke-width": "1",
                "stroke-dasharray": "3 4",
            },
        )
        ET.SubElement(
            svg,
            "line",
            {
                "x1": f"{plot_x0-6}",
                "y1": f"{yy:.2f}",
                "x2": f"{plot_x0}",
                "y2": f"{yy:.2f}",
                "stroke": AXIS_COLOR,
                "stroke-width": "1",
            },
        )
        t = ET.SubElement(
            svg,
            "text",
            {
                "x": f"{plot_x0-10}",
                "y": f"{yy+4:.2f}",
                "font-size": "12",
                "text-anchor": "end",
                "fill": AXIS_COLOR,
            },
        )
        t.text = f"{y_val:.0f} m"
        y_val += y_step

    tx = ET.SubElement(
        svg,
        "text",
        {
            "x": f"{(plot_x0+plot_x1)/2:.2f}",
            "y": f"{SVG_HEIGHT-24}",
            "font-size": "14",
            "text-anchor": "middle",
            "fill": AXIS_COLOR,
        },
    )
    tx.text = "Distancia (m)"
    ty = ET.SubElement(
        svg,
        "text",
        {
            "x": "24",
            "y": f"{(plot_y0+plot_y1)/2:.2f}",
            "font-size": "14",
            "text-anchor": "middle",
            "fill": AXIS_COLOR,
            "transform": f"rotate(-90 24 {(plot_y0+plot_y1)/2:.2f})",
        },
    )
    ty.text = "Altitud (m)"

    ET.SubElement(
        svg,
        "polyline",
        {"points": poly_points, "stroke": PROFILE_STROKE, "stroke-width": PROFILE_STROKE_WIDTH, "fill": "none"},
    )

    svg_out = xml_path.with_name("altimetria.svg")
    ET.ElementTree(svg).write(svg_out, encoding="utf-8", xml_declaration=True)
    print(f"\nSVG generado correctamente → {svg_out}\n")


if __name__ == "__main__":
    script_dir = Path(__file__).resolve().parent
    xml_file = script_dir / "circuitoEsquema.xml"
    generar_altimetria(xml_file)
