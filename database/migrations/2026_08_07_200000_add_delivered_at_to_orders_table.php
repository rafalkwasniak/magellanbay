<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data faktycznego ODBIORU przesyłki przez klienta — wypełniana automatycznie,
 * gdy InPost zgłosi doręczenie. Dla paczkomatu „doręczona" znaczy „klient wyjął
 * paczkę ze skrytki", czyli dokładnie moment, od którego ustawa liczy 14 dni na
 * odstąpienie od umowy.
 *
 * Pole jest NIEOBOWIĄZKOWE i niczego nie wymusza: bez niego termin liczymy jak
 * dotąd (od „Zrealizowane" plus zapas na dostawę), z nim — dokładnie. Dzięki
 * temu zamówienia bez integracji InPost, z kurierem czy z odbiorem osobistym
 * działają bez zmian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
    }
};
