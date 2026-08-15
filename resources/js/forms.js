// Walidacja formularzy po stronie klienta — wyłącznie warstwa UX. Łapie
// oczywiste błędy (pola wymagane, format e-maila, zgodność haseł) ZANIM
// formularz poleci na serwer, żeby nie tracić wpisanych danych (np. hasła) na
// przeładowaniu. Źródłem prawdy pozostają Form Requesty na backendzie — tu nie
// dublujemy reguł biznesowych (zajętość sluga, unikalność e-maila itp.).
//
// Konwencja (trzymamy jej w każdym formularzu):
//   <form novalidate data-validate>           — włącza moduł, wyłącza dymki przeglądarki
//   <input required>                           — pole wymagane (też checkbox i grupa radio)
//   data-msg-required="..."                    — własny komunikat „wymagane"
//   data-match="password"                      — wartość musi równać się polu o tym id
//   data-msg-email / data-msg-match="..."      — własne komunikaty
//
// Zero zależności — spójne z modułem toastów.

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const DEFAULTS = {
    required: 'To pole jest wymagane.',
    requiredCheckbox: 'Zaznacz to pole, aby kontynuować.',
    requiredRadio: 'Wybierz jedną z opcji.',
    email: 'Podaj poprawny adres e-mail.',
    match: 'Pola muszą być identyczne.',
};

function isCheckable(control) {
    return control.type === 'checkbox' || control.type === 'radio';
}

// Radio jest polem GRUPOWYM: „wymagane" znaczy „wybierz coś z grupy", a nie
// „zaznacz akurat ten przycisk". Bez tego rozróżnienia zaznaczenie jednej opcji
// zapalało błąd przy pozostałych — formularz odmawiał wysyłki mimo dokonanego
// wyboru (złapane na ekranie rozstrzygania zgłoszeń, 15.08).
function radioGroup(control, form) {
    return form.querySelectorAll(`input[type="radio"][name="${CSS.escape(control.name)}"]`);
}

function groupChecked(control, form) {
    return Array.from(radioGroup(control, form)).some((radio) => radio.checked);
}

// Komunikat: własny z data-msg-* albo domyślny.
function message(control, key, fallback) {
    const data = control.dataset['msg' + key.charAt(0).toUpperCase() + key.slice(1)];
    return data || fallback;
}

// Element, PO którym wstawiamy komunikat: dla checkboxa cała etykieta, dla
// reszty samo pole (tak jak renderuje serwerowy @error).
function anchorFor(control) {
    return isCheckable(control) ? (control.closest('label') || control) : control;
}

function showError(control, text) {
    control.setAttribute('aria-invalid', 'true');
    if (!isCheckable(control)) {
        control.classList.add('!border-rose-400');
    }

    const anchor = anchorFor(control);
    let p = anchor.nextElementSibling;
    if (!p || !p.hasAttribute('data-js-error')) {
        p = document.createElement('p');
        p.setAttribute('data-js-error', '');
        p.className = 'mt-1.5 text-sm text-rose-600';
        anchor.insertAdjacentElement('afterend', p);
    }
    p.textContent = text;
}

function clearControl(control) {
    control.removeAttribute('aria-invalid');
    control.classList.remove('!border-rose-400');
    const p = anchorFor(control).nextElementSibling;
    if (p && p.hasAttribute('data-js-error')) {
        p.remove();
    }
}

function clearAll(form) {
    form.querySelectorAll('[aria-invalid]').forEach(clearControl);
    form.querySelectorAll('[data-js-error]').forEach((p) => p.remove());
}

// Pierwsza reguła, której pole nie spełnia, albo null gdy OK.
function firstViolation(control, form) {
    const value = control.value.trim();

    if (control.required) {
        if (control.type === 'radio') {
            if (!groupChecked(control, form)) return message(control, 'required', DEFAULTS.requiredRadio);
        } else if (isCheckable(control)) {
            if (!control.checked) return message(control, 'required', DEFAULTS.requiredCheckbox);
        } else if (value === '') {
            return message(control, 'required', DEFAULTS.required);
        }
    }

    if (control.type === 'email' && value !== '' && !EMAIL_RE.test(value)) {
        return message(control, 'email', DEFAULTS.email);
    }

    if (control.dataset.match) {
        const other = form.querySelector('#' + CSS.escape(control.dataset.match));
        if (other && control.value !== '' && control.value !== other.value) {
            return message(control, 'match', DEFAULTS.match);
        }
    }

    return null;
}

function validate(form) {
    clearAll(form);

    const invalid = [];
    // Grupa radio ma dostać JEDEN komunikat, nie tyle, ile ma przycisków —
    // inaczej pod każdą opcją wyskoczyłoby to samo zdanie.
    const sprawdzoneGrupy = new Set();

    form.querySelectorAll('input, select, textarea').forEach((control) => {
        if (control.disabled || control.type === 'hidden') return;

        if (control.type === 'radio') {
            if (sprawdzoneGrupy.has(control.name)) return;
            sprawdzoneGrupy.add(control.name);
        }

        const violation = firstViolation(control, form);
        if (violation !== null) {
            showError(control, violation);
            invalid.push(control);
        }
    });

    return invalid;
}

function initForm(form) {
    if (form.hasAttribute('data-validate-ready')) return;
    form.setAttribute('data-validate-ready', '');

    form.addEventListener('submit', (e) => {
        const invalid = validate(form);
        if (invalid.length === 0) return;

        e.preventDefault();
        invalid[0].focus({ preventScroll: true });
        // 'nearest' — przewijamy tylko gdy pole jest poza widokiem; gdy
        // formularz mieści się w całości, nie ruszamy strony (logo zostaje).
        invalid[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    // Błąd znika, gdy użytkownik zaczyna poprawiać pole.
    const clearIfInvalid = (e) => {
        // Przy grupie radio komunikat wisi pod PIERWSZYM przyciskiem, a kliknąć
        // można dowolny — więc czyścimy całą grupę, nie tylko ten trafiony.
        if (e.target.type === 'radio' && e.target.name) {
            radioGroup(e.target, form).forEach((radio) => {
                if (radio.getAttribute('aria-invalid')) clearControl(radio);
            });

            return;
        }

        if (e.target.getAttribute('aria-invalid')) clearControl(e.target);
    };
    form.addEventListener('input', clearIfInvalid);
    form.addEventListener('change', clearIfInvalid);
}

function initForms(root = document) {
    root.querySelectorAll('form[data-validate]').forEach(initForm);
}

document.addEventListener('DOMContentLoaded', () => initForms());
