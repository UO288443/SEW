class Noticias {
    #busqueda;
    #url;
    #datos;

    constructor() {
        this.#busqueda = "Termas de Río Hondo";
        this.#url = `https://api.thenewsapi.com/v1/news/all?language=en&api_token=Bc85M4H2igEIN0aCI0llRUnuc84ULHyVFq8CffIG&search=${encodeURIComponent(this.#busqueda)}`;
    }

    async buscar() {
        try {
            const respuesta = await fetch(this.#url);

            if (!respuesta.ok) {
                throw new Error(`Error HTTP ${respuesta.status}`);
            }

            this.#datos = await respuesta.json();
            this.#procesarInformacion();
        } catch (error) {
            console.error("Error al obtener noticias:", error);
        }
    }

    #obtenerArticuloReferencia() {
        const $main = $("main");
        if ($main.length === 0) {
            console.error("No se encontró el elemento <main> para insertar noticias.");
            return null;
        }

        let $article = $main.children("article").first();

        // Si aún no existe artículo (por si el carrusel tarda), creamos uno vacío
        if ($article.length === 0) {
            $article = $("<article></article>");
            $main.prepend($article);
        }

        return $article;
    }

    #procesarInformacion() {
        if (!this.#datos || !this.#datos.data) {
            console.warn("No hay datos para procesar. ¿Ejecutaste buscar() primero?");
            return;
        }

        const $article = this.#obtenerArticuloReferencia();
        if (!$article) return;

        const $section = $("<section></section>");
        const $encabezado = $("<h2></h2>").text("Noticias sobre MotoGP");
        $section.append($encabezado);

        this.#datos.data.forEach(noticia => {
            const $titulo = $("<h3></h3>").text(noticia.title);

            const $texto = $("<p></p>").text(
                noticia.description || "Sin descripción disponible."
            );

            const $enlace = $("<p></p>").append(
                $("<a></a>")
                    .attr("href", noticia.url)
                    .attr("target", "_blank")
                    .text("Ver noticia completa")
            );

            const fuente = noticia.source || "Desconocida";
            const $fuente = $("<p></p>").text(`Fuente: ${fuente}`);

            $section.append($titulo, $texto, $enlace, $fuente);
        });

        $article.after($section);
    }
}


const noticias = new Noticias();
noticias.buscar();

