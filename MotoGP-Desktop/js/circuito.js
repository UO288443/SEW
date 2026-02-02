class Circuito {
    #contenidoHTML = "";  

    constructor() {
        this.#cargarArchivo();
    }

    #cargarArchivo() {
        fetch("xml/InfoCircuito.html")
            .then(r => r.text())
            .then(html => {
                this.#contenidoHTML = html;
                this.#representarInfo();
            })
            .catch(err => console.error("No se pudo cargar InfoCircuito.html:", err));
    }

    #representarInfo() {
        const parser = new DOMParser();
        const doc = parser.parseFromString(this.#contenidoHTML, "text/html");

        const head = doc.getElementsByTagName("head")[0];
        head?.parentNode?.removeChild(head);

        const header = doc.getElementsByTagName("header")[0];
        header?.parentNode?.removeChild(header);

        const parrafos = doc.getElementsByTagName("p");
        for (let p of parrafos) {
            if (p.textContent.includes("Usted se encuentra")) {
                p.parentNode.removeChild(p);
                break;
            }
        }

        const bodyContent = doc.body;
        const bodyActual = document.getElementsByTagName("body")[0];

        Array.from(bodyContent.children).forEach(el => {
            bodyActual.appendChild(el);
        });
    }
}
class CargadorSVG{
    #svgContenido = "";
    constructor(){
        this.#asignarEventos();
    }
    
    #leerArchivoSVG(archivo){
        const lector = new FileReader();

        lector.onload = e => {
            this.#svgContenido = e.target.result;   
            this.#insertarSVG();
        };

        lector.readAsText(archivo);
    }

    #insertarSVG() {
        const posibleContenedor = document.body.lastElementChild;

        if (posibleContenedor && posibleContenedor.tagName === "SECTION") {
            posibleContenedor.remove();
        }

        const contenedor = document.createElement("section");

        const titulo = document.createElement("h2");
        titulo.textContent = "SVG cargado";

        contenedor.innerHTML = this.#svgContenido;
        contenedor.prepend(titulo);

        document.body.appendChild(contenedor);
    }




    #asignarEventos() {
        const inputFile = document.querySelector('input[type="file"]');

        if (!inputFile) {
            console.error("No se ha encontrado el elemento input[type='file'] en el documento.");
            return;
        }

        inputFile.addEventListener("change", (e) => {
            const archivo = e.target.files[0];
            if (archivo) {
                this.#leerArchivoSVG(archivo);
            }
        });
    }
}
class CargadorKML{
    #kmlContenido = "";
    constructor(){
        this.#asignarEventos();
    }
    #leerArchivoKML(archivo){
        const lector = new FileReader();

        lector.onload = e => {
            this.#kmlContenido = e.target.result;   
            this.#insertarCapaKML();
        };

        lector.readAsText(archivo);
        console.log(this.#kmlContenido);
    }
    #insertarCapaKML() {
    if (!window.map) return;

    const parser = new DOMParser();
    const xml = parser.parseFromString(this.#kmlContenido, "text/xml");
    const kmlNS = "http://www.opengis.net/kml/2.2";

    const placemarks = xml.getElementsByTagNameNS(kmlNS, "Placemark");

    let origenLat = null;
    let origenLon = null;

    for (let i = 0; i < placemarks.length; i++) {
        const nameNode = placemarks[i].getElementsByTagNameNS(kmlNS, "name")[0];
        if (nameNode && nameNode.textContent.trim() === "Origen") {
            const pointNode = placemarks[i].getElementsByTagNameNS(kmlNS, "Point")[0];
            const coordNode = pointNode?.getElementsByTagNameNS(kmlNS, "coordinates")[0];

            if (coordNode) {
                const partes = coordNode.textContent.trim().split(",");
                const lat = parseFloat(partes[0]);
                const lon = parseFloat(partes[1]);
                origenLat = lat;
                origenLon = lon;
            }
            break;
        }
    }

    if (origenLat == null || origenLon == null) return;

    new mapboxgl.Marker()
        .setLngLat([origenLon, origenLat])
        .setPopup(new mapboxgl.Popup().setText("Origen del circuito"))
        .addTo(window.map);

    let lineStringNode = null;

    for (let i = 0; i < placemarks.length; i++) {
        const ls = placemarks[i].getElementsByTagNameNS(kmlNS, "LineString")[0];
        if (ls) {
            lineStringNode = ls;
            break;
        }
    }

    if (!lineStringNode) return;

    const coordsNode = lineStringNode.getElementsByTagNameNS(kmlNS, "coordinates")[0];
    if (!coordsNode) return;

    const lineas = coordsNode.textContent.trim().split(/\s+/);
    const coords = [];

    const dist2 = (lon, lat) => {
        const dLon = lon - origenLon;
        const dLat = lat - origenLat;
        return dLon * dLon + dLat * dLat;
    };

    for (const linea of lineas) {
        const partes = linea.split(",");
        if (partes.length < 2) continue;

        const a = parseFloat(partes[0]);
        const b = parseFloat(partes[1]);
        if (Number.isNaN(a) || Number.isNaN(b)) continue;

        const lon1 = a, lat1 = b;
        const lon2 = b, lat2 = a;

        const d1 = dist2(lon1, lat1);
        const d2 = dist2(lon2, lat2);

        coords.push(d1 <= d2 ? [lon1, lat1] : [lon2, lat2]);
    }

    if (coords.length === 0) return;

    const geojson = {
        type: "Feature",
        geometry: { type: "LineString", coordinates: coords },
        properties: {}
    };

    if (window.map.getSource("trazadoCircuito")) {
        window.map.getSource("trazadoCircuito").setData(geojson);
    } else {
        window.map.addSource("trazadoCircuito", { type: "geojson", data: geojson });

        window.map.addLayer({
            id: "trazadoCircuito",
            type: "line",
            source: "trazadoCircuito",
            paint: { "line-width": 4 }
        });
    }

    const bounds = coords.reduce(
        (b, c) => b.extend(c),
        new mapboxgl.LngLatBounds(coords[0], coords[0])
    );

    window.map.fitBounds(bounds, { padding: 40 });
}


    #asignarEventos() {
        const inputs = document.querySelectorAll('input[type="file"]');

        const inputKML = inputs[1]; 

        if (!inputKML) {
            console.error("No se ha encontrado el segundo input[type='file'].");
            return;
        }

        inputKML.addEventListener("change", (e) => {
            const archivo = e.target.files[0];
            if (archivo) {
                this.#leerArchivoKML(archivo);
            }
        });
    }

}
new Circuito();
new CargadorSVG();
new CargadorKML();