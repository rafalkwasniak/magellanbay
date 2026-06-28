// Licznik znaków dla edytorów Trix (komponent x-rich-editor). Każdy licznik
// [data-rich-count] wskazuje (data-for) ukryte pole edytora i pokazuje długość
// jego HTML — bo to ona podlega limitowi. Zero zależności.

function updateRichCounts() {
    document.querySelectorAll('[data-rich-count]').forEach((el) => {
        const input = document.getElementById(el.getAttribute('data-for'));
        if (input) el.textContent = input.value.length;
    });
}

document.addEventListener('trix-change', updateRichCounts);
document.addEventListener('trix-initialize', updateRichCounts);
document.addEventListener('DOMContentLoaded', updateRichCounts);
