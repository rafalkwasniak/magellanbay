// Nawigacja mobilna: hamburger otwiera wysuwany panel (drawer) z menu.
// Zero zależności. Otwierany przyciskiem [data-mobile-nav-open]; zamykany
// backdropem, przyciskiem [data-mobile-nav-close], klawiszem Esc oraz po
// kliknięciu w link menu (nawigacja i tak przeładuje stronę).

function initMobileNav() {
    const root = document.querySelector('[data-mobile-nav]');
    if (!root) {
        return;
    }

    const panel = root.querySelector('[data-mobile-nav-panel]');
    const openButton = document.querySelector('[data-mobile-nav-open]');
    let open = false;

    function openNav() {
        open = true;
        root.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // brak przewijania tła pod drawerem
        // Kolejna klatka, żeby przejście slide-in się odpaliło (element już nie jest hidden).
        requestAnimationFrame(() => panel.classList.remove('-translate-x-full'));
    }

    function closeNav() {
        open = false;
        panel.classList.add('-translate-x-full');
        document.body.style.overflow = '';
        // Ukryj dopiero po animacji wysunięcia (czas = duration-300).
        setTimeout(() => {
            if (!open) {
                root.classList.add('hidden');
            }
        }, 300);
    }

    openButton?.addEventListener('click', openNav);

    root.querySelectorAll('[data-mobile-nav-close], [data-mobile-nav-backdrop]')
        .forEach((el) => el.addEventListener('click', closeNav));

    root.querySelectorAll('a').forEach((a) => a.addEventListener('click', closeNav));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && open) {
            closeNav();
        }
    });
}

document.addEventListener('DOMContentLoaded', initMobileNav);
