<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zgody marketingowe klientów — osobno od `customers`, bo to dane DOWODOWE:
 * przy sporze trzeba wykazać, kto, kiedy, z jakiego IP i NA CO się zgodził
 * (RODO art. 7 ust. 1). Kaskada przy usunięciu klienta domyka „prawo do bycia
 * zapomnianym" bez grzebania w kolumnach profilu.
 *
 * Relacja 1:N po KANALE, nie historia — unikat na parę (klient, kanał) daje
 * najwyżej jeden wiersz na kanał. Historii zmian świadomie nie trzymamy: RODO
 * wymaga wykazania zgody AKTUALNEJ, a dowód „nie wysyłaliśmy po wypisie" siedzi
 * w outboksie (`email_messages`), który ma datę każdej wysyłki.
 *
 * `version` to wersja treści zgody z `config/legal.php`. Bez niej zmiana treści
 * w przyszłości cicho unieważniłaby stare zgody — nie dałoby się już odtworzyć,
 * kto na co klikał. U sprzedawców tę rolę pełni `legal_document_id`.
 *
 * `revoked_at` odróżnia „wypisał się" od „nigdy się nie zgodził" — prawnie
 * zbędne, produktowo istotne (kogo nie zaczepiać ponownie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('version', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // Jeden wiersz na kanał — to nie jest dziennik zmian.
            $table->unique(['customer_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_consents');
    }
};
