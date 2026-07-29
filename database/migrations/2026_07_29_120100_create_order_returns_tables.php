<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwroty konsumenckie — oświadczenia o odstąpieniu od umowy (ustawa z 30 maja
 * 2014 o prawach konsumenta). Jedno zgłoszenie = jeden wiersz `order_returns`
 * z pozycjami; jedno zamówienie może mieć ich wiele (klient oddaje partiami).
 *
 * Zgłoszenie jest FAKTEM, nie wnioskiem do rozpatrzenia: odstąpienie działa z
 * mocy prawa i nie wymaga zgody sprzedawcy, więc nie ma tu statusu „odrzucony".
 * Jedyna decyzja sprzedawcy to `refunded_at` — moment, w którym oddał pieniądze
 * (w v1 ręcznie: przelew/zwrot w Paynow i faktura korygująca poza systemem).
 *
 * Dane osobowe (`customer_name`, `customer_address`) to migawka z formularza,
 * a nie odczyt z zamówienia: ustawowy wzór oświadczenia wymaga podania ich
 * przez konsumenta, a adres do odesłania bywa inny niż adres dostawy.
 * `bank_account` jest opcjonalne — potrzebne, gdy pieniędzy nie da się oddać
 * tą samą drogą, którą przyszły.
 *
 * `refund_gross` to migawka kwoty do oddania za pozycje (już po rabacie,
 * zgodnie z tym, co klient faktycznie zapłacił). KOSZTU DOSTAWY tu nie ma —
 * zwrot dostawy sprzedawca rozlicza ręcznie, bo ustawa każe oddać najtańszą
 * OFEROWANĄ dostawę, a klient mógł wybrać droższą.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('customer_name');
            $table->string('customer_address');
            $table->string('bank_account', 34)->nullable();
            // Przyczyna jest DOBROWOLNA — konsument odstępuje bez podania powodu.
            $table->text('note')->nullable();

            $table->decimal('refund_gross', 10, 2);
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();
        });

        Schema::create('order_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained()->cascadeOnDelete();
            // Pozycji ze zwrotem nie da się usunąć z zamówienia (guard w
            // OrderEditor), więc kaskada to tu wyłącznie sprzątanie po
            // skasowanym zamówieniu, nie normalna droga.
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();

            $table->decimal('quantity', 10, 2);
            $table->decimal('refund_gross', 10, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');
    }
};
