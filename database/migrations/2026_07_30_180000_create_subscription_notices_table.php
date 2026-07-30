<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ślad wysłanych powiadomień o abonamencie — po to, by cron chodzący codziennie
 * nie wysłał tego samego przypomnienia drugi raz.
 *
 * Klucz unikalności obejmuje `ends_at`, nie tylko rodzaj: po odnowieniu termin
 * jest inny, więc przypomnienia mogą wyjść ponownie — bez czyszczenia tabeli i
 * bez kombinowania z datami wysyłki.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            // 'reminder_14' / 'reminder_7' / 'reminder_1' / 'locked'
            $table->string('kind', 32);
            // Termin, którego powiadomienie dotyczyło (migawka — po odnowieniu
            // wpis zostaje jako historia, a nowy termin startuje z czystym kontem).
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->unique(['shop_id', 'kind', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_notices');
    }
};
