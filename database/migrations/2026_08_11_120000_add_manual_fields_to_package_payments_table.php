<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wpłata ZAREJESTROWANA RĘCZNIE — przelew albo gotówka przyjęta poza bramką.
 *
 * Powód: pakiet sprzedany z ręki nie zostawiał w bazie ŻADNEGO śladu w
 * złotówkach. `package_changes` zapisywało, że pakiet się zmienił, ale nie
 * kwotę — więc przychód platformy pokazywał zero, choć pieniądze wpłynęły.
 * Zamiast drugiego rejestru dokładamy trzy kolumny do istniejącego: jedno
 * miejsce prawdy o pieniądzach, jeden sposób liczenia przychodu.
 *
 * `method` — 'paynow' (bramka) | 'transfer' | 'cash'. Domyślnie 'paynow', bo
 * wszystkie dotychczasowe wiersze przyszły z bramki.
 *
 * `recorded_by` — kto wpisał wpłatę do systemu. Przy płatności z bramki null:
 * nikt jej nie wpisywał, potwierdził ją webhook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_payments', function (Blueprint $table) {
            $table->string('method', 16)->default('paynow')->after('status');
            $table->foreignId('recorded_by')->nullable()->after('payment_id')->constrained('users')->nullOnDelete();
            $table->string('note')->nullable()->after('invoiced_at');
        });
    }

    public function down(): void
    {
        Schema::table('package_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn(['method', 'note']);
        });
    }
};
