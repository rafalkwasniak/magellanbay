<?php

use App\Enums\ShopStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sklep sprzedawcy. `slug` to etykieta subdomeny ({slug}.{central_domain}) —
 * musi być globalnie unikalny (unique). Powstaje już przy rejestracji jako
 * szkic (status Draft) z zarezerwowaną subdomeną; dane sklepu (adres, telefon)
 * i publikacja przyjdą w kroku aktywacji sklepu. Tabele najemcy (produkty,
 * zamówienia) będą wskazywać na `shops.id` przez `shop_id`.
 *
 * `domain` to opcjonalna dedykowana domena sklepu (np. mojsklep.pl). Gdy jest
 * ustawiona, storefront serwujemy spod niej zamiast subdomeny; pusta = sklep
 * działa pod {slug}.{central_domain}. Unikalna, bo to publiczny adres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique(); // dedykowana domena, np. mojsklep.pl
            $table->string('status')->default(ShopStatus::Draft->value)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
