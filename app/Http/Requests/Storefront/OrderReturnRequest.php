<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Oświadczenie o odstąpieniu od umowy składane przez klienta na publicznym
 * formularzu (link tokenowy, bez logowania).
 *
 * Wymagamy tylko tego, czego wymaga ustawowy wzór oświadczenia: kto odstępuje
 * i spod jakiego adresu. Przyczyna jest DOBROWOLNA — konsument nie musi jej
 * podawać, więc pole `note` nigdy nie może być obowiązkowe. Numer konta też
 * jest opcjonalny: pieniądze wracają domyślnie tą samą drogą, którą przyszły,
 * a konto podaje się tylko wtedy, gdy inaczej się nie da (np. płatność przy
 * odbiorze).
 *
 * Ile czego wraca sprawdza OrderReturnService — on ma blokadę wiersza i zna
 * sufity, więc dwa równoległe zgłoszenia nie oddadzą tej samej sztuki dwa razy.
 * Tutaj pilnujemy wyłącznie kształtu danych.
 */
class OrderReturnRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_address' => ['required', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:34'],
            'note' => ['nullable', 'string', 'max:2000'],

            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Podaj imię i nazwisko.',
            'customer_address.required' => 'Podaj adres.',
            'quantities.required' => 'Zaznacz, co chcesz zwrócić.',
        ];
    }

    /**
     * Normalizacja przed walidacją (konwencja projektu): numer konta bez spacji
     * i myślników, puste teksty na `null`, żeby nie zapisywać pustych stringów.
     */
    protected function prepareForValidation(): void
    {
        $account = $this->input('bank_account');

        $this->merge([
            'bank_account' => filled($account) ? preg_replace('/[\s-]+/', '', (string) $account) : null,
            'note' => filled($this->input('note')) ? trim((string) $this->input('note')) : null,
        ]);
    }

    /**
     * Zwracane ilości jako mapa: id pozycji zamówienia → ilość. Puste pola
     * odpadają — niewypełniona pozycja to po prostu niezaznaczona pozycja.
     *
     * @return array<int, float>
     */
    public function quantities(): array
    {
        $result = [];

        foreach ((array) $this->input('quantities', []) as $itemId => $quantity) {
            if (filled($quantity) && (float) $quantity > 0) {
                $result[(int) $itemId] = (float) $quantity;
            }
        }

        return $result;
    }

    /**
     * Dane oświadczenia w kształcie oczekiwanym przez OrderReturnService.
     *
     * @return array<string, string|null>
     */
    public function declaration(): array
    {
        return [
            'customer_name' => $this->string('customer_name')->trim()->value(),
            'customer_address' => $this->string('customer_address')->trim()->value(),
            'bank_account' => $this->input('bank_account'),
            'note' => $this->input('note'),
        ];
    }
}
