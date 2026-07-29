<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Korespondencja seryjna sklepu — jedna wiadomość do klientów ze zgodą.
 *
 * Mailing jest bytem ROBOCZYM: sprzedawca pisze treść, wysyła próbki na własny
 * adres ile razy chce, poprawia i dopiero na końcu puszcza ją do wszystkich.
 * `sent_at` jest więc granicą — dopóki jest puste, wiadomość można edytować
 * i testować; po wysyłce staje się zapisem historycznym (jedna wiadomość leci
 * do klientów DOKŁADNIE RAZ, kolejna dopiero po karencji).
 *
 * `recipients_count` to migawka z chwili wysyłki: ilu klientów miało wtedy
 * aktywną zgodę. Nie da się jej odtworzyć później (klienci się wypisują), a
 * jest potrzebna, by uczciwie pokazać historię.
 *
 * `test_sends` = licznik próbek do siebie. Czysto informacyjny, ale mówi
 * sprzedawcy „sprawdziłeś to trzy razy", zanim naciśnie wysyłkę do wszystkich.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_mailings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('subject');
            $table->text('body');

            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->nullable();
            $table->unsignedInteger('test_sends')->default(0);

            $table->timestamps();

            // Karencję liczymy z ostatniej wysyłki sklepu — po tym szukamy.
            $table->index(['shop_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_mailings');
    }
};
