<?php

namespace App\Enums;

/**
 * Typy integracji per-sklep (kolumna `type` na shop_integrations). Na razie
 * jedna: Google Analytics / Tag Manager. Kolejne (PayU, InPost, …) dokładamy
 * jako nowe case'y — bez migracji („rozbudowa bez przebudowy").
 */
enum IntegrationType: string
{
    case GoogleAnalytics = 'google_analytics';

    /**
     * Czytelna nazwa (do UI).
     */
    public function label(): string
    {
        return match ($this) {
            self::GoogleAnalytics => 'Google Analytics',
        };
    }
}
