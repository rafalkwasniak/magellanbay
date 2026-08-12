<?php

namespace App\Services;

use App\Models\ReservedSlug;
use App\Models\Shop;

/**
 * Czy dana etykieta subdomeny jest dziś do wzięcia?
 *
 * Pytanie zadaje strona nieistniejącego storefrontu, żeby wiedzieć, czy wolno
 * napisać „ten adres jest wolny". Odpowiedź MUSI zgadzać się z tym, co za
 * chwilę zrobi walidacja rejestracji (`RegisterRequest`) — obiecany adres,
 * którego formularz nie przyjmie, jest gorszy niż brak obietnicy. Dlatego oba
 * miejsca czytają te same źródła: `config('tenancy')`, tabelę `shops` i
 * kwarantannę `reserved_slugs`. Zgodność pilnuje test.
 *
 * Unikalności e-maila ani niczego spoza adresu tu nie ma — to jest wyłącznie
 * pytanie o adres.
 */
class SubdomainAvailability
{
    public function isFree(string $slug): bool
    {
        return $this->hasValidShape($slug)
            && ! in_array($slug, (array) config('tenancy.reserved_subdomains'), true)
            && ! Shop::query()->where('slug', $slug)->exists()
            && ! ReservedSlug::query()->active()->where('slug', $slug)->exists();
    }

    /**
     * Kształt etykiety DNS: małe litery i cyfry, myślniki tylko w środku,
     * długość w granicach z `config('tenancy.subdomain')`. Lustro reguły
     * `slug` z RegisterRequest.
     */
    private function hasValidShape(string $slug): bool
    {
        $length = strlen($slug);

        return $length >= (int) config('tenancy.subdomain.min')
            && $length <= (int) config('tenancy.subdomain.max')
            && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
}
