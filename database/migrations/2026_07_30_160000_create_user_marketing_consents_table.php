<?php

use App\Enums\ConsentChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zgoda SPRZEDAWCY na informacje handlowe od Kramio (kody, nowości, oferty).
 *
 * Osobna tabela od `user_consents`, bo tam żyją akceptacje DOKUMENTÓW prawnych
 * (regulamin, polityka) — zupełnie inny byt: obowiązkowy, wersjonowany
 * dokumentem, bez pojęcia kanału i bez możliwości wycofania. Zgoda marketingowa
 * jest dobrowolna, kanałowa i odwoływalna, więc kopiujemy kształt sprawdzony
 * u klientów sklepów (`customer_consents`).
 *
 * Maile NIEZBĘDNE DO UMOWY (faktura, wygaśnięcie pakietu, awaria, zmiana
 * regulaminu) NIE wymagają tej zgody i nigdy nie mogą jej pytać. Dotyczy
 * wyłącznie treści handlowych — art. 10 uśude.
 *
 * BACKFILL: kontom istniejącym w chwili migracji nadajemy zgodę. To trzy konta
 * testowe założycieli (Rafał + kolega), za jego wyraźną zgodą — nie żaden
 * masowy import cudzych adresów.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_marketing_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('version', 16)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'channel']);
        });

        $now = now();

        foreach (DB::table('users')->pluck('id') as $userId) {
            DB::table('user_marketing_consents')->insert([
                'user_id' => $userId,
                'channel' => ConsentChannel::Email->value,
                'granted_at' => $now,
                'version' => config('legal.seller_marketing_consent.version', 'v1'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_marketing_consents');
    }
};
