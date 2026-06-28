// „Pobierz dane z NIP": pobiera nazwę firmy i adres z Białej listy MF i wypełnia
// pola formularza. Przycisk aktywny dopiero gdy NIP ma 10 cyfr. Zero zależności;
// korzysta z window.showToast (resources/js/app.js).

function digitsOnly(value) {
    return (value || '').replace(/\D/g, '');
}

function setField(id, value) {
    if (value === undefined || value === null || value === '') return;
    const el = document.getElementById(id);
    if (el) el.value = value;
}

function toastFrom(response) {
    if (response && typeof response.json === 'function') {
        response.json()
            .then((d) => window.showToast(d.message || 'Nie udało się pobrać danych.', 'error'))
            .catch(() => window.showToast('Nie udało się pobrać danych.', 'error'));
    } else {
        window.showToast('Nie udało się pobrać danych.', 'error');
    }
}

function initNipLookup() {
    const button = document.querySelector('[data-nip-lookup]');
    const nip = document.getElementById('nip');
    if (!button || !nip) return;

    const labelEl = button.querySelector('[data-nip-text]');
    const originalLabel = labelEl ? labelEl.textContent : '';

    const sync = () => { button.disabled = digitsOnly(nip.value).length !== 10; };
    nip.addEventListener('input', sync);
    sync();

    button.addEventListener('click', () => {
        if (button.disabled) return;
        const token = document.querySelector('meta[name="csrf-token"]');

        button.disabled = true;
        if (labelEl) labelEl.textContent = 'Pobieram…';

        fetch(button.getAttribute('data-url'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify({ nip: digitsOnly(nip.value) }),
        })
            .then((r) => (r.ok ? r.json() : Promise.reject(r)))
            .then((data) => {
                setField('company_name', data.company_name);
                setField('street', data.street);
                setField('building_number', data.building_number);
                setField('apartment_number', data.apartment_number);
                setField('postal_code', data.postal_code);
                setField('city', data.city);
                setField('province', data.province);
                // Rejestry są krajowe — domyślnie Polska, gdy pole Kraj puste.
                const country = document.getElementById('country');
                if (country && country.value.trim() === '') country.value = 'Polska';
                const hint = data.province ? 'Pobrano dane firmy. Sprawdź i zapisz.' : 'Pobrano dane firmy. Sprawdź adres i wybierz województwo.';
                window.showToast(hint, 'success');
            })
            .catch(toastFrom)
            .finally(() => {
                if (labelEl) labelEl.textContent = originalLabel;
                sync();
            });
    });
}

document.addEventListener('DOMContentLoaded', initNipLookup);
