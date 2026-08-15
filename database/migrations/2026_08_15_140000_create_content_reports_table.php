<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zgłoszenia treści bezprawnych — mechanizm z art. 16 DSA (notice and action).
 *
 * DLACZEGO: Kramio hostuje publicznie treści sprzedawców, więc jest dostawcą
 * usługi hostingu w rozumieniu DSA. Art. 16 i 17 nie mają wyłączenia dla
 * mikroprzedsiębiorców — obowiązują niezależnie od tego, czy jesteśmy platformą
 * internetową (nie jesteśmy, patrz §4 Regulaminu).
 *
 * Powód ważniejszy niż sama zgodność: §14 ust. 1 Regulaminu („Operator nie
 * odpowiada za treści Sprzedawców") stoi na wyłączeniu odpowiedzialności z art. 6
 * DSA, które działa dopóki nie wiemy o bezprawnej treści, a gdy się dowiemy —
 * działamy niezwłocznie. Bez rejestru wiedzę można nam było przekazać mailem,
 * a my nie mieliśmy jak wykazać, co z nią zrobiliśmy.
 *
 * `shop_id` NULLOWALNE i `nullOnDelete`: sklep może zniknąć między zgłoszeniem
 * a rozpatrzeniem (usunięcie ma 7 dni karencji), a zgłoszenie musi zostać w
 * rejestrze razem z decyzją. Adres zgłoszonej treści trzymamy w `url`, więc
 * historia jest czytelna nawet bez sklepu.
 *
 * `good_faith` wygląda na kolumnę zawsze prawdziwą i taka jest — to nie flaga
 * sterująca, tylko DOWÓD, że zgłaszający złożył oświadczenie wymagane przez
 * art. 16 ust. 2 lit. d. Ta sama logika, co przy zgodach marketingowych.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();

            $table->string('url', 2048);
            $table->string('category', 32);
            $table->text('justification');

            $table->string('reporter_name')->nullable();
            $table->string('reporter_email');
            $table->boolean('good_faith')->default(false);
            $table->string('ip_address', 45)->nullable();

            $table->string('status', 16)->default('new')->index();
            $table->text('decision_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();

            // Potwierdzenie odbioru wymagane „bez zbędnej zwłoki" (art. 16 ust. 4).
            // Znacznik, a nie bool — przy sporze liczy się KIEDY, nie CZY.
            $table->timestamp('acknowledged_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};
