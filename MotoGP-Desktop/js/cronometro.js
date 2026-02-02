class Cronometro {
    #tiempo;
    constructor(){
        this.#tiempo = 0;
        this.#mostrar();
        this.#asignarEventosABotones();
    }
    arrancar(){
        try{
            this.inicio = Temporal.Now.intant()
        }catch {
            this.inicio = Date.now();
        }
        this.corriendo = setInterval(this.#actualizar.bind(this),100);
        this.#mostrar();
    }

    #actualizar(){
        let actual;
        try{
            actual = Temporal.Now.intant()

        }catch {
            actual = Date.now();
        }
        this.#tiempo = actual - this.inicio;
        this.#mostrar();
    }

    #mostrar(){
        const totalMs = this.#tiempo;

        const minutos  = parseInt(totalMs / 60000);
        const segundos = parseInt((totalMs % 60000) / 1000);
        const decimas  = parseInt((totalMs % 1000) / 100);

        const mm = String(minutos).padStart(2, '0');
        const ss = String(segundos).padStart(2, '0');
        const s  = String(decimas); 

        const texto = `Cronómetro :${mm}:${ss}.${s}`;
        document.querySelector("main p").textContent = texto;
        
    }
    reiniciar(){
        clearInterval(this.corriendo);
        this.#tiempo = 0;
        this.#mostrar();
    }
    parar(){
        clearInterval(this.corriendo);
    }

    #asignarEventosABotones(){
        const botones = document.querySelectorAll("body > button");
        if(botones.length !=3) return;
        const [btnArrancar, btnParar, btnReiniciar] = botones;
        btnArrancar.addEventListener("click", () => this.arrancar());
        btnParar.addEventListener("click", () => this.parar());
        btnReiniciar.addEventListener("click", () => this.reiniciar());
    }
}
const cronometro = new Cronometro();