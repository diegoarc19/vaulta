/**
 * dark-mode.js + mobile sidebar — Vaulta
 *
 * 1. Aplica dark mode INMEDIATAMENTE (antes del primer paint)
 * 2. Inyecta el botón toggle de dark mode en .top-bar
 * 3. Inyecta el botón hamburguesa y gestiona el sidebar en móvil
 */

// ─── 1. Aplicar clase INMEDIATAMENTE antes del render ─────────────────────
(function () {
    try {
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.classList.add('dark-mode');
            if (document.body) document.body.classList.add('dark-mode');
        }
    } catch (e) { /* localStorage no disponible */ }
})();

// ─── 2. Cuando el DOM esté listo ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var body = document.body;
    var isDark = localStorage.getItem('darkMode') === 'enabled';

    // Sincronizar body
    if (isDark) {
        body.classList.add('dark-mode');
    } else {
        body.classList.remove('dark-mode');
        document.documentElement.classList.remove('dark-mode');
    }

    // ── A) DARK MODE TOGGLE en .top-bar ───────────────────────────────────
    var topBar = document.querySelector('.top-bar');

    if (topBar && !document.getElementById('darkModeToggle')) {
        var btn = document.createElement('button');
        btn.id = 'darkModeToggle';
        btn.className = 'btn-dark-mode';
        btn.setAttribute('aria-label', 'Alternar modo oscuro');
        btn.setAttribute('title', 'Modo oscuro');
        btn.setAttribute('type', 'button');
        btn.innerHTML = '<i class="fas ' + (isDark ? 'fa-sun' : 'fa-moon') + '"></i>';
        topBar.appendChild(btn);

        btn.addEventListener('click', function () {
            var nowDark = body.classList.toggle('dark-mode');
            document.documentElement.classList.toggle('dark-mode', nowDark);
            var icon = btn.querySelector('i');
            if (nowDark) {
                localStorage.setItem('darkMode', 'enabled');
                icon.classList.replace('fa-moon', 'fa-sun');
            } else {
                localStorage.setItem('darkMode', 'disabled');
                icon.classList.replace('fa-sun', 'fa-moon');
            }
        });
    }

    // ── B) HAMBURGER MENU para el sidebar ─────────────────────────────────
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar) return; // No hay sidebar → salir (páginas de auth)

    // Crear overlay si no existe
    var overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        body.appendChild(overlay);
    }

    // Crear botón hamburguesa si no existe
    var hamburger = document.getElementById('menuToggle');
    if (!hamburger) {
        hamburger = document.createElement('button');
        hamburger.id = 'menuToggle';
        hamburger.className = 'menu-toggle';
        hamburger.setAttribute('aria-label', 'Abrir menú');
        hamburger.setAttribute('type', 'button');
        hamburger.innerHTML = '<i class="fas fa-bars"></i>';
        body.insertBefore(hamburger, body.firstChild);
    }

    // ── Funciones de apertura/cierre ──────────────────────────────────────
    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        hamburger.setAttribute('aria-expanded', 'true');
        hamburger.innerHTML = '<i class="fas fa-times"></i>';
        body.style.overflow = 'hidden'; // Evitar scroll del fondo
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        hamburger.setAttribute('aria-expanded', 'false');
        hamburger.innerHTML = '<i class="fas fa-bars"></i>';
        body.style.overflow = '';
    }

    // Toggle al pulsar hamburguesa
    hamburger.addEventListener('click', function () {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    // Cerrar al pulsar el overlay
    overlay.addEventListener('click', closeSidebar);

    // Cerrar con ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    // Cerrar al hacer clic en un enlace del nav (mejora UX móvil)
    var navLinks = sidebar.querySelectorAll('.nav-links a');
    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    // ── C) Cerrar sidebar al redimensionar a escritorio ───────────────────
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth > 768) {
                closeSidebar();
                body.style.overflow = '';
            }
        }, 150);
    });

    // ── D) Añadir hint de scroll en tablas ────────────────────────────────
    if (window.innerWidth <= 768) {
        var tableContainers = document.querySelectorAll(
            '.movements-section, .recent-transfers, .recurring-section'
        );
        tableContainers.forEach(function (container) {
            var table = container.querySelector('table');
            if (table && !container.querySelector('.scroll-hint')) {
                var hint = document.createElement('p');
                hint.className = 'scroll-hint';
                hint.innerHTML = '<i class="fas fa-arrows-alt-h"></i> Desliza para ver más';
                container.insertBefore(hint, table);
            }
        });
    }
});
