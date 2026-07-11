// Kolejność stron „Informacje" — drag & drop na pionowej liście w panelu.
// Po upuszczeniu zapisujemy nową kolejność AJAX-em (bez przeładowania). Ta sama
// kolejność rządzi menu i stopką storefrontu. Zero zależności (poza window.showToast).

function csrf() {
    const token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
}

function toast(message, type) {
    if (window.showToast) window.showToast(message, type);
}

function initList(list) {
    const reorderUrl = list.dataset.reorderUrl;
    const items = () => Array.from(list.querySelectorAll('[data-page-item]'));

    const persistOrder = () => {
        fetch(reorderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ order: items().map((it) => it.dataset.id) }),
        })
            .then((r) => { if (!r.ok) toast('Nie udało się zapisać kolejności.', 'error'); })
            .catch(() => toast('Nie udało się zapisać kolejności.', 'error'));
    };

    let dragged = null;

    list.addEventListener('dragstart', (e) => {
        const item = e.target.closest('[data-page-item]');
        if (!item) return;
        dragged = item;
        item.classList.add('opacity-50');
    });

    list.addEventListener('dragend', () => {
        if (!dragged) return;
        dragged.classList.remove('opacity-50');
        dragged = null;
        persistOrder();
    });

    list.addEventListener('dragover', (e) => {
        e.preventDefault();
        const target = e.target.closest('[data-page-item]');
        if (!target || !dragged || target === dragged) return;
        const rect = target.getBoundingClientRect();
        const after = e.clientY - rect.top > rect.height / 2;
        list.insertBefore(dragged, after ? target.nextSibling : target);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-page-list]').forEach(initList);
});
