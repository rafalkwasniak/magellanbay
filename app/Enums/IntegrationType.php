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
    case Invoicing = 'invoicing';
    case Payments = 'payments';
    case SearchConsole = 'search_console';
    case Shipping = 'shipping';

    /**
     * Czytelna nazwa (do UI).
     */
    public function label(): string
    {
        return match ($this) {
            self::GoogleAnalytics => 'Google Analytics',
            self::Invoicing => 'Fakturownia',
            self::Payments => 'Płatności online (Paynow)',
            self::SearchConsole => 'Google Search Console',
            self::Shipping => 'Nadawanie przesyłek InPost',
        };
    }
}
