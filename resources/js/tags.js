// Pole tagów „chipsowe": owalne tagi z ✕, dodawanie Enter/przecinkiem,
// podpowiedzi z istniejących tagów sklepu, usuwanie Backspace/✕. Zero zależności.
// Serwer i tak normalizuje autorytatywnie (TagNormalizer) — tu lustro dla UX.

function normalizeClient(value) {
    let s = (value || '').trim().replace(/\s*-\s*/g, '-').replace(/\s+/g, ' ').toLowerCase();
    s = s.replace(/^[^\p{L}\p{N}]+/u, '').replace(/[^\p{L}\p{N}]+$/u, '');
    return s.slice(0, 50);
}

function initTagInput(root) {
    const hidden = root.querySelector('[data-tag-value]');
    const box = root.querySelector('[data-tag-box]');
    const text = root.querySelector('[data-tag-text]');
    const dropdown = root.querySelector('[data-tag-suggestions]');
    if (!hidden || !box || !text || !dropdown) return;

    let suggestions = [];
    try {
        suggestions = JSON.parse(root.dataset.suggestions || '[]');
    } catch (e) {
        suggestions = [];
    }

    let tags = (hidden.value || '')
        .split(',')
        .map(normalizeClient)
        .filter((t, i, a) => t && a.indexOf(t) === i);

    const syncHidden = () => { hidden.value = tags.join(','); };

    const render = () => {
        box.querySelectorAll('[data-tag-chip]').forEach((el) => el.remove());
        tags.forEach((name) => {
            const chip = document.createElement('span');
            chip.setAttribute('data-tag-chip', '');
            chip.className = 'inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800';
            chip.textContent = name;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.setAttribute('aria-label', 'Usuń tag');
            remove.className = 'text-amber-600 transition hover:text-amber-900';
            remove.textContent = '✕';
            remove.addEventListener('click', () => { removeTag(name); });

            chip.appendChild(remove);
            box.insertBefore(chip, text);
        });
        syncHidden();
    };

    function addTag(raw) {
        const name = normalizeClient(raw);
        if (name && !tags.includes(name)) {
            tags.push(name);
            render();
        }
        text.value = '';
        hideDropdown();
    }

    function removeTag(name) {
        tags = tags.filter((t) => t !== name);
        render();
        text.focus();
    }

    const hideDropdown = () => { dropdown.classList.add('hidden'); dropdown.innerHTML = ''; };

    const showDropdown = () => {
        const query = normalizeClient(text.value);
        const matches = suggestions
            .filter((s) => !tags.includes(s) && (query === '' || s.includes(query)))
            .slice(0, 8);

        if (matches.length === 0) { hideDropdown(); return; }

        dropdown.innerHTML = '';
        matches.forEach((s) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'block w-full px-3 py-2 text-left text-sm text-stone-700 transition hover:bg-amber-50';
            item.textContent = s;
            item.addEventListener('mousedown', (e) => { e.preventDefault(); addTag(s); text.focus(); });
            dropdown.appendChild(item);
        });
        dropdown.classList.remove('hidden');
    };

    text.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addTag(text.value);
        } else if (e.key === 'Backspace' && text.value === '' && tags.length) {
            removeTag(tags[tags.length - 1]);
        }
    });
    text.addEventListener('input', showDropdown);
    text.addEventListener('focus', showDropdown);
    text.addEventListener('blur', () => { setTimeout(() => { addTag(text.value); }, 120); });
    box.addEventListener('click', () => text.focus());

    render();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tag-input]').forEach(initTagInput);
});
