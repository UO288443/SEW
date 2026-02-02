/**
 * Clase que gestiona el comportamiento del menú responsive
 * sin usar id, class ni div en el HTML.
 */
class ResponsiveMenu {
    /**
     * @param {HTMLElement} headerElement - Elemento <header> que contiene el botón y el nav
     * @param {number} breakpoint - Ancho a partir del cual se considera escritorio
     */
    constructor(headerElement, breakpoint = 768) {
        this.header = headerElement;
        this.breakpoint = breakpoint;

        // Buscamos el botón y el nav dentro del header
        this.button = this.header.querySelector("button");
        this.nav = this.header.querySelector("nav");

        // Si falta algo, no seguimos
        if (!this.button || !this.nav) {
            return;
        }

        // Enlazamos los métodos al contexto de la instancia
        this.onToggleClick = this.onToggleClick.bind(this);
        this.onWindowResize = this.onWindowResize.bind(this);

        // Estado inicial
        this.init();
    }

    /**
     * Configura el estado inicial y eventos
     */
    init() {
        // Respetamos el estado inicial indicado en el HTML (data-open)
        const isOpen = this.nav.getAttribute("data-open") === "true";
        this.button.setAttribute("aria-expanded", String(isOpen));

        // Añadimos los manejadores de eventos
        this.button.addEventListener("click", this.onToggleClick);
        window.addEventListener("resize", this.onWindowResize);

        // Ajustamos una primera vez según el tamaño actual
        this.updateStateForCurrentWidth();
    }

    /**
     * Maneja el click sobre el botón del menú
     */
    onToggleClick() {
        const isExpanded = this.button.getAttribute("aria-expanded") === "true";
        const newExpandedState = !isExpanded;

        // Actualizamos aria-expanded para accesibilidad
        this.button.setAttribute("aria-expanded", String(newExpandedState));

        // Abrimos/cerramos el menú cambiando el data-atributo
        this.nav.setAttribute("data-open", newExpandedState ? "true" : "false");
    }

    /**
     * Maneja el evento de redimensionado de ventana
     */
    onWindowResize() {
        this.updateStateForCurrentWidth();
    }

    /**
     * Actualiza el estado del menú según el ancho actual de la ventana
     */
    updateStateForCurrentWidth() {
        const width = window.innerWidth;

        // Si estamos en escritorio, nos aseguramos de que el menú esté visible
        if (width > this.breakpoint) {
            this.nav.setAttribute("data-open", "true");
            // El botón en escritorio realmente no se usa, pero dejamos aria-expanded en false
            this.button.setAttribute("aria-expanded", "false");
        }
        // En móvil dejamos que mande el estado actual (data-open) controlado por el botón
    }

    /**
     * Método estático de ayuda para inicializar desde el DOM
     */
    static initFromDocument() {
        const header = document.querySelector("header");
        if (!header) {
            return null;
        }
        return new ResponsiveMenu(header);
    }
}

// Inicializamos cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function () {
    ResponsiveMenu.initFromDocument();
});
