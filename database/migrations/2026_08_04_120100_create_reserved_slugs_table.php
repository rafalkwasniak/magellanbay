<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kwarantanna adresów po usuniętych sklepach. Adres (etykieta subdomeny) nie
 * wraca do puli od razu, bo stare linki, maile do klientów i wyniki w Google
 * prowadziłyby wtedy do CUDZEGO sklepu pod znanym adresem.
 *
 * Osobna tabela, a nie flaga na `shops`, bo rezerwacja MUSI przeżyć usunięcie
 * sklepu — to jedyny ślad, jaki po nim zostaje.
 *
 * `released_at` = chwila, od której adres znów wolno zająć. Wpisy po terminie
 * sprząta `shops:purge`, ale walidacja i tak patrzy na datę, więc zaległy wpis
 * niczego nie blokuje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserved_slugs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->timestamp('released_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserved_slugs');
    }
};
