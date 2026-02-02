"""
IMPORTANTE: SE DEBE EJECUTAR DESDE LA TERMINAL

python xml2kml.py
"""

import xml.etree.ElementTree as ET
from xml.dom import minidom
from pathlib import Path

INPUT = Path("circuitoEsquema.xml")
OUTPUT = Path("circuito.kml")

KML_NS = "http://www.opengis.net/kml/2.2"
NS_UNIOVI = "{https://www.uniovi.es/}"

ET.register_namespace("", KML_NS)


def gettext(root, path):
    el = root.find(path)
    return el.text.strip() if el is not None and el.text else ""


def add_point_placemark(parent, name, description, lon, lat, alt=None):
    pm = ET.SubElement(parent, ET.QName(KML_NS, "Placemark"))
    n = ET.SubElement(pm, ET.QName(KML_NS, "name"))
    n.text = name
    d = ET.SubElement(pm, ET.QName(KML_NS, "description"))
    d.text = description
    pt = ET.SubElement(pm, ET.QName(KML_NS, "Point"))
    coords = ET.SubElement(pt, ET.QName(KML_NS, "coordinates"))
    coords.text = f"{lon},{lat},{alt}" if (alt is not None and str(alt) != "") else f"{lon},{lat}"
    ET.SubElement(pt, ET.QName(KML_NS, "altitudeMode")).text = "relativeToGround"
    return pm


def build_kml(root):
    ns = NS_UNIOVI

    nombre_circuito = gettext(root, f"{ns}nombre_circuito")
    localidad = gettext(root, f"{ns}localidad_proxima")
    pais = gettext(root, f"{ns}pais")
    longitud_total = gettext(root, f"{ns}longitud_circuito")
    long_el = root.find(f"{ns}longitud_circuito")
    unid_long = long_el.get("unidades") if long_el is not None else ""
    anchura = gettext(root, f"{ns}anchura")
    fecha = gettext(root, f"{ns}fecha_carrera_2025")
    hora_es = gettext(root, f"{ns}hora_carrera_españa")

    kml = ET.Element(ET.QName(KML_NS, "kml"))
    doc = ET.SubElement(
        kml,
        ET.QName(KML_NS, "Document"),
        attrib={"id": nombre_circuito or "Circuito"},
    )

    name_el = ET.SubElement(doc, ET.QName(KML_NS, "name"))
    name_el.text = nombre_circuito or "Circuito"

    desc_el = ET.SubElement(doc, ET.QName(KML_NS, "description"))
    desc_el.text = (
        f"{localidad}, {pais}. "
        f"Longitud: {longitud_total}{unid_long}. "
        f"Anchura: {anchura}m. Carrera: {fecha} {hora_es}"
    )

    coords_list = []

    orig_lon = gettext(root, f"{ns}origen_circuito/{ns}coordenadas/{ns}longitud")
    orig_lat = gettext(root, f"{ns}origen_circuito/{ns}coordenadas/{ns}latitud")
    orig_alt = gettext(root, f"{ns}origen_circuito/{ns}coordenadas/{ns}altitud")
    if orig_lon and orig_lat:
        add_point_placemark(
            doc,
            "Origen",
            "Punto de referencia del circuito",
            orig_lon,
            orig_lat,
            orig_alt or None,
        )
        coords_list.append((orig_lon, orig_lat, orig_alt or ""))

    for punto in root.findall(f"{ns}puntos/{ns}punto"):
        dist_el = punto.find(f"{ns}distancia")
        sector_el = punto.find(f"{ns}sector")
        dist = dist_el.text.strip() if dist_el is not None and dist_el.text else ""
        sector = sector_el.text.strip() if sector_el is not None and sector_el.text else ""
        lon_el = punto.find(f"{ns}coordenadas/{ns}longitud")
        lat_el = punto.find(f"{ns}coordenadas/{ns}latitud")
        alt_el = punto.find(f"{ns}coordenadas/{ns}altitud")
        lon = lon_el.text.strip() if lon_el is not None and lon_el.text else ""
        lat = lat_el.text.strip() if lat_el is not None and lat_el.text else ""
        alt = alt_el.text.strip() if alt_el is not None and alt_el.text else ""
        if lon and lat:
            add_point_placemark(
                doc,
                f"Punto {dist} m",
                f"Sector {sector or '-'} · Distancia {dist} m",
                lon,
                lat,
                alt or None,
            )
            coords_list.append((lon, lat, alt or ""))

    if coords_list:
        pm_track = ET.SubElement(doc, ET.QName(KML_NS, "Placemark"))
        ET.SubElement(pm_track, ET.QName(KML_NS, "name")).text = "Trazado del circuito"
        ET.SubElement(
            pm_track, ET.QName(KML_NS, "description")
        ).text = "Secuencia de puntos del recorrido"
        line = ET.SubElement(pm_track, ET.QName(KML_NS, "LineString"))
        ET.SubElement(line, ET.QName(KML_NS, "tessellate")).text = "1"
        ET.SubElement(line, ET.QName(KML_NS, "altitudeMode")).text = "relativeToGround"
        coords_el = ET.SubElement(line, ET.QName(KML_NS, "coordinates"))
        rows = [
            f"{lon},{lat},{alt}" if alt else f"{lon},{lat}"
            for lon, lat, alt in coords_list
        ]
        coords_el.text = "\n".join(rows)

    return kml


def main():
    tree = ET.parse(INPUT)
    root = tree.getroot()
    kml = build_kml(root)
    rough = ET.tostring(kml, encoding="utf-8", xml_declaration=True)
    pretty = minidom.parseString(rough).toprettyxml(
        indent="  ", encoding="UTF-8"
    )
    with open(OUTPUT, "wb") as f:
        f.write(pretty)
    print(f"KML generado correctamente: {OUTPUT}")


if __name__ == "__main__":
    main()
