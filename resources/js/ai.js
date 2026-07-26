// „Popraw przez AI": wysyła treść pola do endpointu redakcji i wstawia poprawiony
// wynik z powrotem. Limit użyć per pole/ładowanie strony pilnuje atrybut
// data-ai-uses. Zero zależności; korzysta z window.showToast (resources/js/app.js).
//
// Świadome edytora Trix: gdy cel to ukryte pole spięte z <trix-editor>, wysyłamy
// HTML (Trix synchronizuje go do inputa) i ładujemy poprawiony HTML z powrotem,
// więc formatowanie zostaje zachowane. Serwer dobiera tryb (html/tekst) per pole.
// Wzorzec przeniesiony z kociaczek.com.pl.
//
// DZIELENIE NA FRAGMENTY: korekta trwa tyle, ile zajmuje PRZEPISANIE tekstu na
// wyjściu, więc czas rośnie liniowo z długością pola — całego regulaminu nie da
// się poprawić jednym wywołaniem, bo przekroczyłoby timeout. Tniemy więc treść
// tutaj, po stronie przeglądarki, i wysyłamy fragmenty po kolei. Robienie tego
// na serwerze nic by nie dało: jedno żądanie HTTP dalej trwałoby minuty.
//
// Ta sama ścieżka obowiązuje wszystkie pola — krótki tekst wychodzi jako jeden
// fragment. Każde pole z <x-rich-editor> dostaje to automatycznie.

const CHUNK_CHARS_FALLBACK = 1200;

// Ile fragmentów leci naraz. Czekamy wtedy tyle, ile trwa najdłuższy, a nie ich
// suma — na opisie produktu to różnica między 19 a 10 sekundami. Okno jest wąskie
// celowo: kilkanaście jednoczesnych wywołań to lawina kosztu w jednej sekundzie.
const CONCURRENCY = 3;

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

// Zdejmuje opakowanie z odpowiedzi modelu, żeby dało się skleić kawałki jednego
// bloku z powrotem w jeden. Model zwraca fragment tak, jak go dostał — w <div>.
function unwrap(html) {
    const body = new DOMParser().parseFromString(html, 'text/html').body;

    return body.children.length === 1 ? body.children[0].innerHTML : body.innerHTML;
}

// Tnie zawartość JEDNEGO bloku po <br>. Bez tego wklejony regulamin — który Trix
// trzyma jako jeden <div> z dwustoma <br> w środku — szedłby w całości i wracał
// dopiero po kilku minutach albo wcale. Każda paczka dostaje z powrotem to samo
// opakowanie, żeby model widział poprawny HTML.
function splitBlock(element, limit, group) {
    const outer = element.outerHTML;
    const open = outer.slice(0, outer.indexOf('>') + 1);
    const close = `</${element.tagName.toLowerCase()}>`;
    const lines = element.innerHTML.split(/<br\s*\/?>/i);

    const groups = [];
    let buffer = [];
    let length = 0;

    lines.forEach((line) => {
        // Pojedyncza linia dłuższa niż limit leci sama — cięcie w środku zdania
        // rozbijałoby sens i model gubiłby wątek.
        if (buffer.length && length + line.length > limit) {
            groups.push(buffer.join('<br>'));
            buffer = [];
            length = 0;
        }
        buffer.push(line);
        length += line.length + 4;
    });

    if (buffer.length) groups.push(buffer.join('<br>'));

    return groups.map((inner) => ({
        send: open + inner + close,
        group,
        wrapper: { open, close },
    }));
}

// Tnie HTML na fragmenty: najpierw po blokach najwyższego poziomu (akapit,
// nagłówek, lista), a blok większy niż limit — dodatkowo w środku, po <br>.
function splitHtml(html, limit) {
    const body = new DOMParser().parseFromString(html, 'text/html').body;
    const blocks = Array.from(body.children);

    // Goła treść bez bloków (albo coś, czego nie umiemy podzielić) — jeden fragment.
    if (!blocks.length) return [{ send: html, group: null, wrapper: null }];

    const chunks = [];
    let buffer = '';

    const flush = () => {
        if (buffer) chunks.push({ send: buffer, group: null, wrapper: null });
        buffer = '';
    };

    blocks.forEach((element, index) => {
        const piece = element.outerHTML;

        if (piece.length > limit) {
            flush();
            splitBlock(element, limit, index).forEach((chunk) => chunks.push(chunk));

            return;
        }

        if (buffer && buffer.length + piece.length > limit) flush();
        buffer += piece;
    });

    flush();

    return chunks.length ? chunks : [{ send: html, group: null, wrapper: null }];
}

// Składa wyniki z powrotem. Kawałki pochodzące z jednego bloku wracają do jednego
// bloku — inaczej podzielony regulamin wróciłby jako kilkanaście osobnych akapitów,
// czyli z cicho przebudowaną strukturą, o którą nikt nie prosił.
function reassemble(chunks, results, separator) {
    const out = [];
    let i = 0;

    while (i < chunks.length) {
        const { group, wrapper } = chunks[i];

        if (group === null) {
            out.push(results[i]);
            i += 1;
            continue;
        }

        const parts = [];

        while (i < chunks.length && chunks[i].group === group) {
            parts.push(unwrap(results[i]));
            i += 1;
        }

        out.push(wrapper.open + parts.join('<br>') + wrapper.close);
    }

    return out.join(separator);
}

// Zwykły tekst tniemy po akapitach (pusta linia), tą samą zasadą co HTML.
function splitText(text, limit) {
    const paragraphs = text.split(/\n{2,}/);
    const chunks = [];
    let buffer = '';

    paragraphs.forEach((paragraph) => {
        if (buffer && buffer.length + paragraph.length > limit) {
            chunks.push(buffer);
            buffer = '';
        }
        buffer += (buffer ? '\n\n' : '') + paragraph;
    });

    if (buffer) chunks.push(buffer);

    return (chunks.length ? chunks : [text]).map((send) => ({ send, group: null, wrapper: null }));
}

function splitContent(target, text, limit) {
    return trixFor(target.id) ? splitHtml(text, limit) : splitText(text, limit);
}

// Jeden fragment → jedno wywołanie endpointu.
function sendChunk(button, chunk) {
    const token = document.querySelector('meta[name="csrf-token"]');

    return fetch(button.getAttribute('data-ai-url'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
        },
        body: JSON.stringify({ field: button.getAttribute('data-ai-field'), text: chunk }),
    }).then((response) => (response.ok ? response.json() : Promise.reject(response)));
}

async function improveWithAi(button) {
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
    const limit = parseInt(button.getAttribute('data-ai-chunk'), 10) || CHUNK_CHARS_FALLBACK;
    const chunks = splitContent(target, payload(target), limit);

    button.disabled = true;

    // Postęp: fragmenty pokazują, ile zostało, sekundy — że cokolwiek się dzieje.
    // Bez sekund etykieta potrafi stać nieruchomo przez minutę i wygląda jak
    // zawieszona strona, co kusi do odświeżenia w połowie roboty.
    const startedAt = Date.now();
    let done = 0;
    const tick = () => {
        const seconds = Math.round((Date.now() - startedAt) / 1000);
        // Numer fragmentu W TRAKCIE, nie liczba ukończonych — inaczej licznik
        // startuje od „0 z 3", a „3 z 3" nigdy się nie pokazuje, bo tuż po nim
        // pętla się kończy. Ograniczenie do liczby fragmentów pilnuje, żeby po
        // ostatnim nie wyskoczyło „4 z 3".
        const current = Math.min(done + 1, chunks.length);
        labelEl.textContent = chunks.length > 1
            ? `Poprawiam… ${current} z ${chunks.length} · ${seconds} s`
            : `Poprawiam… ${seconds} s`;
    };
    tick();
    const ticker = setInterval(tick, 1000);

    // Wynik każdego fragmentu ląduje na swoim miejscu; brak wyniku = zostaje
    // oryginał, więc częściowa awaria nie kasuje tego, co się udało.
    const results = chunks.map((chunk) => chunk.send);
    let failed = false;
    let next = 0;

    const worker = async () => {
        while (!failed) {
            const index = next;
            next += 1;
            if (index >= chunks.length) return;

            try {
                results[index] = (await sendChunk(button, chunks[index].send)).text;
                done += 1;
                tick();
            } catch {
                failed = true;

                return;
            }
        }
    };

    await Promise.all(
        Array.from({ length: Math.min(CONCURRENCY, chunks.length) }, () => worker())
    );

    clearInterval(ticker);

    // HTML sklejamy wprost (bloki niosą własne znaczniki), zwykły tekst — pustą
    // linią, czyli tym, po czym go dzieliliśmy.
    if (done) writeValue(target, reassemble(chunks, results, trixFor(target.id) ? '' : '\n\n'));

    if (failed) {
        window.showToast(
            done
                ? `Poprawiono ${done} z ${chunks.length} fragmentów — resztę zostawiliśmy bez zmian. Spróbuj ponownie za chwilę.`
                : 'Usługa AI jest chwilowo niedostępna. Spróbuj ponownie później.',
            'error'
        );
    }

    // Kliknięcie liczy się jako jedno użycie niezależnie od liczby fragmentów.
    used += 1;
    button.setAttribute('data-ai-uses', used);

    if (used >= max) {
        labelEl.textContent = 'Wykorzystano limit poprawek';
        button.disabled = true;
    } else {
        labelEl.textContent = originalLabel;
        button.disabled = false;
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-ai-button]');
    if (button) improveWithAi(button);
});
