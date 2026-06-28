// „Popraw przez AI": wysyła treść pola do endpointu redakcji i wstawia poprawiony
// tekst z powrotem do pola. Limit użyć per pole/ładowanie strony pilnuje atrybut
// data-ai-uses. Zero zależności; korzysta z window.showToast (resources/js/app.js).
// Wzorzec przeniesiony z kociaczek.com.pl.

function improveWithAi(button) {
    const target = document.getElementById(button.getAttribute('data-ai-target'));
    if (!target || button.disabled) return;

    const max = parseInt(button.getAttribute('data-ai-max'), 10) || 3;
    let used = parseInt(button.getAttribute('data-ai-uses') || '0', 10);
    if (used >= max) return;

    const text = target.value.trim();
    if (text === '') {
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
        body: JSON.stringify({ field: button.getAttribute('data-ai-field'), text: text }),
    })
        .then((response) => (response.ok ? response.json() : Promise.reject(response)))
        .then((data) => {
            target.value = data.text;
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
