class Ciudad {
    #nombre;
    #país;
    #gentilicio; 
    #poblacion;
    #coordenadasCentro;
    #url = "https://archive-api.open-meteo.com/v1/archive";
    #jsonData;
    #jsonDataEntrenos;

    constructor(nombre, país, gentilicio) {
        this.#nombre = nombre;
        this.#país = país;
        this.#gentilicio = gentilicio;
    }

    #inicializaValores() {
        this.#poblacion = 32166;
        this.#coordenadasCentro = { lat: -27.49362, lon: -64.85972 };
    }

    #nombreCiudad() { return this.#nombre; }
    #nombrePaís() { return this.#país; }

    #informacionSecundaria() {
        const ul = document.createElement("ul");

        const liGentilicio = document.createElement("li");
        liGentilicio.textContent = `Gentilicio: ${this.#gentilicio}`;
        ul.appendChild(liGentilicio);

        const liPoblacion = document.createElement("li");
        liPoblacion.textContent = `Población: ${this.#poblacion}`;
        ul.appendChild(liPoblacion);

        return ul;
    }

    #escribirInformacionCoordenadas() {
        const mensaje = document.createElement("p");
        mensaje.textContent = `Coordenadas del centro de la ciudad: Latitud ${this.#coordenadasCentro.lat}, Longitud ${this.#coordenadasCentro.lon}`;
        document.body.appendChild(mensaje);
    }

    async mostrarInformacion() {
        this.#inicializaValores();

        const infoSection = document.createElement("section");
        const nombreCiudad = document.createElement("h2");
        nombreCiudad.textContent = `${this.#nombreCiudad()} => ${this.#nombrePaís()}`;
        infoSection.appendChild(nombreCiudad);

        infoSection.appendChild(this.#informacionSecundaria());

        document.body.appendChild(infoSection);
        this.#escribirInformacionCoordenadas();

        await this.#getMeteorologiaCarrera();
        this.#procesarJSONCarrera();
        await this.#getMeteorologiaEntrenos();
        this.#procesarJSONEntrenos();
    }

    async #getMeteorologiaCarrera() {
        const params = new URLSearchParams({
            latitude: this.#coordenadasCentro.lat,
            longitude: this.#coordenadasCentro.lon,
            start_date: "2025-03-16",
            end_date: "2025-03-16",
            hourly: [
                "temperature_2m",
                "apparent_temperature",
                "precipitation",
                "relative_humidity_2m",
                "wind_speed_10m",
                "wind_direction_10m"
            ].join(","),
            daily: ["sunrise", "sunset"].join(","),
            timezone: "America/Argentina/Buenos_Aires"
        });

        const url = `${this.#url}?${params.toString()}`;

        this.#jsonData = await $.ajax({
            url: url,
            method: "GET",
            dataType: "json"
        });
    }

    #procesarJSONCarrera() {
        if (!this.#jsonData) {
            console.error("No hay datos meteorológicos disponibles");
            return;
        }

        const HORA_INICIO = 19;
        const HORA_FIN = 21;

        const formatearHoraLocal = (isoLocal) => {
            const [fecha, hhmm] = isoLocal.split("T");
            const [y, m, d] = fecha.split("-");
            return `${d}/${m}/${y} ${hhmm}`;
        };

        const meteoSection = document.createElement("section");

        const titulo = document.createElement("h3");
        titulo.textContent = "Información Meteorológica de la carrera (19:00–21:00)";
        meteoSection.appendChild(titulo);

        const tabla = document.createElement("table");

        // Caption accesible
        const caption = document.createElement("caption");
        caption.textContent = "Resultados meteorológicos de la carrera entre las 19:00 y las 21:00 horas";
        tabla.appendChild(caption);

        // Cabecera accesible con id y scope="col"
        const headerRow = document.createElement("tr");
        const headers = [
            { id: "hora",       texto: "Hora" },
            { id: "temp",       texto: "Temp (°C)" },
            { id: "sens",       texto: "Sens. térmica (°C)" },
            { id: "lluvia",     texto: "Lluvia (mm)" },
            { id: "humedad",    texto: "Humedad (%)" },
            { id: "viento",     texto: "Viento (km/h)" },
            { id: "direccion",  texto: "Dirección (°)" }
        ];

        headers.forEach(h => {
            const th = document.createElement("th");
            th.textContent = h.texto;
            th.id = h.id;              // id SOLO dentro de la tabla
            th.scope = "col";
            headerRow.appendChild(th);
        });
        tabla.appendChild(headerRow);

        const h = this.#jsonData.hourly;

        for (let i = 0; i < h.time.length; i++) {
            const t = h.time[i];
            const horaNum = parseInt(t.slice(11, 13), 10);

            if (horaNum >= HORA_INICIO && horaNum <= HORA_FIN) {
                const fila = document.createElement("tr");
                const fechaLegible = formatearHoraLocal(t);

                const celdas = [
                    fechaLegible,
                    h.temperature_2m[i],
                    h.apparent_temperature[i],
                    h.precipitation[i],
                    h.relative_humidity_2m[i],
                    h.wind_speed_10m[i],
                    h.wind_direction_10m[i]
                ];

                // Cada <td> referencia su cabecera mediante headers=""
                celdas.forEach((valor, index) => {
                    const td = document.createElement("td");
                    td.textContent = valor;
                    td.setAttribute("headers", headers[index].id); // headers SOLO dentro de la tabla
                    fila.appendChild(td);
                });

                tabla.appendChild(fila);
            }
        }

        meteoSection.appendChild(tabla);

        const d = this.#jsonData.daily;
        const salida = new Date(d.sunrise[0]).toLocaleString("es-AR", {
            dateStyle: "full",
            timeStyle: "short"
        });
        const puesta = new Date(d.sunset[0]).toLocaleString("es-AR", {
            dateStyle: "full",
            timeStyle: "short"
        });

        const resumen = document.createElement("p");

        const strongSalida = document.createElement("strong");
        strongSalida.textContent = "Salida del sol:";
        resumen.appendChild(strongSalida);
        resumen.appendChild(document.createTextNode(` ${salida}`));

        resumen.appendChild(document.createElement("br"));

        const strongPuesta = document.createElement("strong");
        strongPuesta.textContent = "Puesta del sol:";
        resumen.appendChild(strongPuesta);
        resumen.appendChild(document.createTextNode(` ${puesta}`));

        meteoSection.appendChild(resumen);

        document.body.appendChild(meteoSection);
    }

    async #getMeteorologiaEntrenos(){
        const params = new URLSearchParams({
            latitude: this.#coordenadasCentro.lat,
            longitude: this.#coordenadasCentro.lon,
            start_date: "2025-03-14",
            end_date: "2025-03-15",
            hourly: [
                "temperature_2m",
                "apparent_temperature",
                "precipitation",
                "relative_humidity_2m",
                "wind_speed_10m",
                "wind_direction_10m"
            ].join(","),
            daily: ["sunrise", "sunset"].join(","),
            timezone: "America/Argentina/Buenos_Aires"
        });

        const url = `${this.#url}?${params.toString()}`;

        this.#jsonDataEntrenos = await $.ajax({
            url: url,
            method: "GET",
            dataType: "json"
        });

    }

    #procesarJSONEntrenos() {
        if (!this.#jsonDataEntrenos) {
            console.error("No hay datos meteorológicos de entrenamientos");
            return;
        }

        const formatearHoraLocal = (isoLocal) => {
            const [fecha, hhmm] = isoLocal.split("T");
            const [y, m, d] = fecha.split("-");
            return `${d}/${m}/${y} ${hhmm}`;
        };

        const horariosEntrenos = [
            { fecha: "2025-03-14", inicio: "14:45", fin: "15:30" },
            { fecha: "2025-03-14", inicio: "19:00", fin: "20:00" },
            { fecha: "2025-03-15", inicio: "13:25", fin: "13:55" },
            { fecha: "2025-03-15", inicio: "14:50", fin: "15:05" },
            { fecha: "2025-03-15", inicio: "15:15", fin: "15:30" },
        ];

        const h = this.#jsonDataEntrenos.hourly;
        const meteoSection = document.createElement("section");

        const titulo = document.createElement("h3");
        titulo.textContent = "Información Meteorológica - Entrenamientos";
        meteoSection.appendChild(titulo);

        const tabla = document.createElement("table");

        // Caption accesible
        const caption = document.createElement("caption");
        caption.textContent = "Resultados meteorológicos durante las sesiones de entrenamientos";
        tabla.appendChild(caption);

        // Cabecera accesible con id y scope="col"
        const headerRow = document.createElement("tr");
        const headers = [
            { id: "fecha_intervalo", texto: "Fecha / Intervalo" },
            { id: "hora_entreno",    texto: "Hora" },
            { id: "temp_entreno",    texto: "Temp (°C)" },
            { id: "lluvia_entreno",  texto: "Lluvia (mm)" },
            { id: "humedad_entreno", texto: "Humedad (%)" },
            { id: "viento_entreno",  texto: "Viento (km/h)" }
        ];

        headers.forEach(hd => {
            const th = document.createElement("th");
            th.textContent = hd.texto;
            th.id = hd.id;           // id SOLO dentro de la tabla
            th.scope = "col";
            headerRow.appendChild(th);
        });
        tabla.appendChild(headerRow);

        for (const sesion of horariosEntrenos) {
            const [anio, mes, dia] = sesion.fecha.split("-");
            const inicioH = parseInt(sesion.inicio.slice(0, 2), 10);
            const finH = parseInt(sesion.fin.slice(0, 2), 10);

            for (let i = 0; i < h.time.length; i++) {
                const t = h.time[i];
                if (!t.startsWith(sesion.fecha)) continue;

                const horaNum = parseInt(t.slice(11, 13), 10);
                if (horaNum >= inicioH && horaNum <= finH) {
                    const fila = document.createElement("tr");

                    const celdas = [
                        `${dia}/${mes}/${anio} (${sesion.inicio}–${sesion.fin})`,
                        formatearHoraLocal(t),
                        h.temperature_2m[i],
                        h.precipitation[i],
                        h.relative_humidity_2m[i],
                        h.wind_speed_10m[i]
                    ];

                    celdas.forEach((valor, index) => {
                        const td = document.createElement("td");
                        td.textContent = valor;
                        td.setAttribute("headers", headers[index].id); // headers SOLO dentro de la tabla
                        fila.appendChild(td);
                    });

                    tabla.appendChild(fila);
                }
            }
        }

        meteoSection.appendChild(tabla);

        const d = this.#jsonDataEntrenos.daily;
        const salida = new Date(d.sunrise[0]).toLocaleString("es-AR", {
            dateStyle: "full",
            timeStyle: "short"
        });
        const puesta = new Date(d.sunset[0]).toLocaleString("es-AR", {
            dateStyle: "full",
            timeStyle: "short"
        });

        const resumen = document.createElement("p");

        const strongSalida = document.createElement("strong");
        strongSalida.textContent = "Salida del sol:";
        resumen.appendChild(strongSalida);
        resumen.appendChild(document.createTextNode(` ${salida}`));

        resumen.appendChild(document.createElement("br"));

        const strongPuesta = document.createElement("strong");
        strongPuesta.textContent = "Puesta del sol:";
        resumen.appendChild(strongPuesta);
        resumen.appendChild(document.createTextNode(` ${puesta}`));

        meteoSection.appendChild(resumen);

        document.body.appendChild(meteoSection);
    }
}

const ciudad = new Ciudad("Termas de Río Hondo", "Argentina", "Termeño/a");
ciudad.mostrarInformacion();
