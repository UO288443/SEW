class Memoria {
    #tablero_bloqueado;
    #primera_carta;
    #segunda_carta;
    #cronometro;

    constructor() {
        const cartas = document.querySelectorAll('main article');
        for (const carta of cartas) {
            carta.setAttribute('onclick', 'memoria.voltearCarta(this)');
        }

        this.#tablero_bloqueado = false;
        this.#primera_carta = null;
        this.#segunda_carta = null;
        this.#cronometro = new Cronometro();
        this.#cronometro.reiniciar();
        this.#cronometro.arrancar();
        this.#barajarCartas();
    }

    voltearCarta(carta) {
        if (this.#tablero_bloqueado) return;
        if (carta.revelada) return;
        if (this.#primera_carta === carta) return;

        if (this.#primera_carta === null) {
            carta.dataset.estado = "volteada";
            this.#primera_carta = carta;
        } else {
            carta.dataset.estado = "volteada";
            this.#segunda_carta = carta;
            this.#comprobarPareja();
        }
    }

    // Métodos privados
    #barajarCartas() {
        const main = document.querySelector('main');
        const cartas = Array.from(main.querySelectorAll('article'));
        const n = cartas.length;

        for (let i = n - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [cartas[i], cartas[j]] = [cartas[j], cartas[i]];
        }

        for (const carta of cartas) {
            main.appendChild(carta);
        }
    }

    #reiniciarAtributos() {
        this.#tablero_bloqueado = false;
        this.#primera_carta = null;
        this.#segunda_carta = null;
    }

    #deshabilitarCartas() {
        this.#primera_carta.revelada = true;
        this.#primera_carta.dataset.estado = "revelada";
        this.#segunda_carta.dataset.estado = "revelada";
        this.#segunda_carta.revelada = true;
        this.#reiniciarAtributos();

        this.#comprobarJuego();
    }

    #comprobarJuego() {
        const main = document.querySelector('main');
        const cartas = Array.from(main.querySelectorAll('article'));
        const n = cartas.length;
        for (let i = n - 1; i > 0; i--) {
            if (!cartas[i].revelada) {
                return;
            }
        }
        this.#cronometro.parar();
    }

    #cubrirCartas() {
        this.#tablero_bloqueado = true;

        setTimeout(() => {
            this.#primera_carta.dataset.estado = null;
            this.#segunda_carta.dataset.estado = null;
            this.#reiniciarAtributos();
        }, 1500);
    }

    #comprobarPareja() {
        if (this.#primera_carta.dataset.estado === "volteada" && this.#segunda_carta.dataset.estado === "volteada") {
            const img1 = this.#primera_carta.querySelector('img');
            const img2 = this.#segunda_carta.querySelector('img');

            const valor1 = img1.getAttribute('src');
            const valor2 = img2.getAttribute('src');
            (valor1 === valor2) ? this.#deshabilitarCartas() : this.#cubrirCartas();
        }
    }
}

const memoria = new Memoria();
