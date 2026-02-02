class Carrusel {
    #busqueda;
    #actual;
    #maximo;
    #flickrAPI = "https://api.flickr.com/services/feeds/photos_public.gne?jsoncallback=?";
    #datosJSON;
    #fotos;
    #img; 

    constructor() {
        this.#busqueda = "Termas de Río Hondo, MotoGP";
        this.#actual = 0;
        this.#maximo = 5;
        this.#datosJSON = null;
        this.#fotos = [];
        this.#img = null;
    }

    getFotografias() {
        const self = this;

        $.getJSON(this.#flickrAPI, {
            tags: this.#busqueda,
            tagmode: "any",
            format: "json"
        })
        .done(function(data) {
            self.#datosJSON = data;
            self.#procesarJSONFotografias();
            self.#mostrarFotografias();
        });
    }

    #procesarJSONFotografias() {
        if (!this.#datosJSON) {
            console.error("No hay datos JSON disponibles.");
            return;
        }

        this.#fotos = this.#datosJSON.items.slice(0, this.#maximo).map(item => ({
            titulo: item.title,
            autor: item.author,
            enlace: item.link,
            imagen: item.media.m.replace("_m.", "_z."), 
            etiquetas: item.tags
        }));
    }

    #mostrarFotografias() {
        if (!this.#fotos.length) {
            console.warn("No hay fotos procesadas para mostrar.");
            return;
        }

        const $main = $("main");
        if ($main.length === 0) {
            console.error("No se encontró el elemento <main> en el documento.");
            return;
        }

        const $article = $("<article></article>");
        const $h2 = $("<h2></h2>").text("Imágenes del circuito de " + this.#busqueda);

        const foto = this.#fotos[this.#actual];

        this.#img = $("<img>")
            .attr("src", foto.imagen)
            .attr("alt", foto.titulo)
            .attr("title", foto.titulo);

        $article.append($h2, this.#img);
        $main.prepend($article);

        setInterval(this.#cambiarFotografia.bind(this), 3000);
    }

    #cambiarFotografia() {
        if (!this.#fotos.length || !this.#img) return;

        this.#actual += 1;
        if (this.#actual >= this.#fotos.length) {
            this.#actual = 0;
        }

        const nuevaFoto = this.#fotos[this.#actual];

        this.#img
            .attr("src", nuevaFoto.imagen)
            .attr("alt", nuevaFoto.titulo)
            .attr("title", nuevaFoto.titulo);
    }
}


const carrusel = new Carrusel();
carrusel.getFotografias();

