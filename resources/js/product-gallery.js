// Galeria zdjęć produktu (karta wewnątrz formularza produktu, akcje przez fetch
// — bez zagnieżdżonych formularzy i bez przeładowania, więc nie tracisz wpisanych
// danych). Dodawanie, usuwanie i zmiana kolejności (drag & drop + strzałki ◀ ▶).
// Pierwsze zdjęcie = główne. Zero zależności (poza window.showToast).

function csrf() {
    const token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
}

function toast(message, type) {
    if (window.showToast) window.showToast(message, type);
}

function buildItem(image) {
    const item = document.createElement('div');
    item.setAttribute('data-gallery-item', '');
    item.setAttribute('data-id', image.id);
    item.setAttribute('draggable', 'true');
    item.className = 'relative cursor-move rounded-2xl border border-stone-200 bg-stone-50 p-2';
    item.innerHTML =
        '<div class="flex h-28 items-center justify-center overflow-hidden rounded-xl bg-white">'
        + '<img src="' + image.url + '" alt="Zdjęcie produktu" draggable="false" class="h-full w-auto object-contain">'
        + '</div>'
        + '<span data-main-badge class="absolute left-3 top-3 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700 hidden">Główne</span>'
        + '<div class="mt-2 flex items-center justify-between gap-2">'
        + '<div class="flex items-center gap-1">'
        + '<button type="button" data-move="prev" aria-label="Przesuń wcześniej" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs text-stone-600 transition hover:bg-stone-100 disabled:opacity-40">◀</button>'
        + '<button type="button" data-move="next" aria-label="Przesuń później" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs text-stone-600 transition hover:bg-stone-100 disabled:opacity-40">▶</button>'
        + '</div>'
        + '<button type="button" data-gallery-delete data-url="' + image.deleteUrl + '" class="text-xs font-medium text-rose-700 transition hover:text-rose-800">Usuń</button>'
        + '</div>';
    return item;
}

function initGallery(container) {
    const card = container.parentElement;
    const reorderUrl = container.dataset.reorderUrl;
    const storeUrl = container.dataset.storeUrl;
    const max = parseInt(container.dataset.max, 10) || 5;
    const uploader = card.querySelector('[data-gallery-uploader]');
    const fileInput = card.querySelector('[data-gallery-upload]');
    const countEl = card.querySelector('[data-gallery-count]');
    const fullNote = card.querySelector('[data-gallery-full]');

    const items = () => Array.from(container.querySelectorAll('[data-gallery-item]'));

    const refresh = () => {
        const all = items();
        all.forEach((item, index) => {
            const badge = item.querySelector('[data-main-badge]');
            if (badge) badge.classList.toggle('hidden', index !== 0);
            const prev = item.querySelector('[data-move="prev"]');
            const next = item.querySelector('[data-move="next"]');
            if (prev) prev.disabled = index === 0;
            if (next) next.disabled = index === all.length - 1;
        });
        if (countEl) countEl.textContent = all.length;
        container.classList.toggle('hidden', all.length === 0);
        if (uploader) uploader.classList.toggle('hidden', all.length >= max);
        if (fullNote) fullNote.classList.toggle('hidden', all.length < max);
    };

    const persistOrder = () => {
        fetch(reorderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ order: items().map((it) => it.dataset.id) }),
        }).then((r) => { if (!r.ok) toast('Nie udało się zapisać kolejności.', 'error'); })
            .catch(() => toast('Nie udało się zapisać kolejności.', 'error'));
    };

    // Dodawanie zdjęć
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const files = Array.from(fileInput.files || []);
            if (files.length === 0) return;

            const data = new FormData();
            files.forEach((f) => data.append('images[]', f));
            fileInput.disabled = true;

            fetch(storeUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: data,
            })
                .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
                .then(({ ok, body }) => {
                    if (!ok) { toast(body.message || 'Nie udało się dodać zdjęć.', 'error'); return; }
                    (body.images || []).forEach((image) => container.appendChild(buildItem(image)));
                    refresh();
                    toast('Dodano zdjęcia.', 'success');
                })
                .catch(() => toast('Nie udało się dodać zdjęć.', 'error'))
                .finally(() => { fileInput.value = ''; fileInput.disabled = false; });
        });
    }

    // Usuwanie + strzałki (delegacja na siatce)
    container.addEventListener('click', (e) => {
        const del = e.target.closest('[data-gallery-delete]');
        if (del) {
            if (!confirm('Usunąć to zdjęcie?')) return;
            fetch(del.dataset.url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } })
                .then((r) => {
                    if (!r.ok) { toast('Nie udało się usunąć zdjęcia.', 'error'); return; }
                    del.closest('[data-gallery-item]').remove();
                    refresh();
                })
                .catch(() => toast('Nie udało się usunąć zdjęcia.', 'error'));
            return;
        }

        const move = e.target.closest('[data-move]');
        if (!move) return;
        const item = move.closest('[data-gallery-item]');
        if (move.dataset.move === 'prev' && item.previousElementSibling) {
            container.insertBefore(item, item.previousElementSibling);
        } else if (move.dataset.move === 'next' && item.nextElementSibling) {
            container.insertBefore(item.nextElementSibling, item);
        } else {
            return;
        }
        refresh();
        persistOrder();
    });

    // Drag & drop
    let dragged = null;
    container.addEventListener('dragstart', (e) => {
        const item = e.target.closest('[data-gallery-item]');
        if (!item) return;
        dragged = item;
        item.classList.add('opacity-50');
    });
    container.addEventListener('dragend', () => {
        if (!dragged) return;
        dragged.classList.remove('opacity-50');
        dragged = null;
        refresh();
        persistOrder();
    });
    container.addEventListener('dragover', (e) => {
        e.preventDefault();
        const target = e.target.closest('[data-gallery-item]');
        if (!target || !dragged || target === dragged) return;
        const rect = target.getBoundingClientRect();
        const after = e.clientX - rect.left > rect.width / 2;
        container.insertBefore(dragged, after ? target.nextSibling : target);
    });

    refresh();
}

// Podgląd zdjęć wybranych przy TWORZENIU produktu (przed zapisem). Pliki lecą
// z formularzem produktu; tu tylko miniatury podglądu.
function initNewImages(root) {
    const input = root.querySelector('[data-new-images-input]');
    const preview = root.querySelector('[data-new-images-preview]');
    if (!input || !preview) return;

    input.addEventListener('change', () => {
        preview.innerHTML = '';
        const files = Array.from(input.files || []).slice(0, 5);
        files.forEach((file) => {
            const card = document.createElement('div');
            card.className = 'rounded-2xl border border-stone-200 bg-stone-50 p-2';
            const wrap = document.createElement('div');
            wrap.className = 'flex h-28 items-center justify-center overflow-hidden rounded-xl bg-white';
            const img = document.createElement('img');
            img.className = 'h-full w-auto object-contain';
            img.src = URL.createObjectURL(file);
            wrap.appendChild(img);
            card.appendChild(wrap);
            preview.appendChild(card);
        });
        preview.classList.toggle('hidden', files.length === 0);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-gallery]').forEach(initGallery);
    document.querySelectorAll('[data-new-images]').forEach(initNewImages);
});
