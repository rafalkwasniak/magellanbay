<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konta klientów storefrontu. Kluczowe zasady ze specyfikacji (sek. „Logowanie
 * i uwierzytelnianie"):
 * - Klient jest klientem KONKRETNEGO sklepu — `shop_id` na każdym wierszu.
 * - Pełna separacja między sklepami: ten sam e-mail może mieć niezależne konto
 *   w wielu sklepach → unikalność złożona `[shop_id, email]`, nie sam e-mail.
 * - Konto powstaje nieaktywne (bez hasła); hasło ustawiane z linku mailowego,
 *   `email_verified_at` = moment aktywacji. `password` i `email_verified_at`
 *   nullable, bo między rejestracją a kliknięciem w mail konto istnieje pusto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->string('surname')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();

            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            $table->timestamps();

            // Ten sam e-mail = różne konta w różnych sklepach; unikalny w obrębie sklepu.
            $table->unique(['shop_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
