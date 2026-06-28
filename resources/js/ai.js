// „Popraw przez AI": wysyła treść pola do endpointu redakcji i wstawia poprawiony
// wynik z powrotem. Limit użyć per pole/ładowanie strony pilnuje atrybut
// data-ai-uses. Zero zależności; korzysta z window.showToast (resources/js/app.js).
//
// Świadome edytora Trix: gdy cel to ukryte pole spięte z <trix-editor>, wysyłamy
// HTML (Trix synchronizuje go do inputa) i ładujemy poprawiony HTML z powrotem,
// więc formatowanie zostaje zachowane. Serwer dobiera tryb (html/tekst) per pole.
// Wzorzec przeniesiony z kociaczek.com.pl.

function trixFor(id) {
    return document.querySelector('trix-editor[input="' + id + '"]');
}

// Treść wysyłana do AI: dla Trix to HTML z ukrytego inputa, dla zwykłego pola jego wartość.
function payload(target) {
    return (target.value || '').trim();
}

// Czy pole jest puste — dla Trix liczy się widoczny tekst, nie same znaczniki.
function isBlank(target) {
    const trix = trixFor(target.id);
    const visible = trix ? trix.editor.getDocument().toString() : target.value;
    return (visible || '').trim() === '';
}

// Wstaw wynik: dla Trix ładujemy HTML (zachowuje formatowanie), dla zwykłego pola — wartość.
function writeValue(target, text) {
    const trix = trixFor(target.id);
    if (trix) trix.editor.loadHTML(text);
    else target.value = text;
}

function improveWithAi(button) {
    const target = document.getElementById(button.getAttribute('data-ai-target'));
    if (!target || button.disabled) return;

    const max = parseInt(button.getAttribute('data-ai-max'), 10) || 3;
    let used = parseInt(button.getAttribute('data-ai-uses') || '0', 10);
    if (used >= max) return;

    if (isBlank(target)) {
        window.showToast('Najpierw wpisz treść do poprawy.', 'error');
        return;
    }

    const labelEl = button.querySelector('[data-ai-text]');
    const originalLabel = button.getAttribute('data-ai-label');
    const token = document.querySelector('meta[name="csrf-token"]');

    button.disabled = true;
    labelEl.textContent = 'Poprawiam…';

    fetch(button.getAttribute('data-ai-url'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
        },
        body: JSON.stringify({ field: button.getAttribute('data-ai-field'), text: payload(target) }),
    })
        .then((response) => (response.ok ? response.json() : Promise.reject(response)))
        .then((data) => {
            writeValue(target, data.text);
            used += 1;
            button.setAttribute('data-ai-uses', used);
            if (used >= max) {
                labelEl.textContent = 'Wykorzystano limit poprawek';
                button.disabled = true;
            } else {
                labelEl.textContent = originalLabel;
                button.disabled = false;
            }
        })
        .catch(() => {
            labelEl.textContent = originalLabel;
            button.disabled = used >= max;
            window.showToast('Usługa AI jest chwilowo niedostępna. Spróbuj ponownie później.', 'error');
        });
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-ai-button]');
    if (button) improveWithAi(button);
});
