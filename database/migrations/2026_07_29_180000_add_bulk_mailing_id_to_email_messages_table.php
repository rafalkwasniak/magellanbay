<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Powiązanie wiadomości w outboxie z mailingiem, z którego powstała — po to,
 * by pokazać sprzedawcy POSTĘP wysyłki („Wysyłam 153 z 350").
 *
 * Bez tego nie da się odróżnić maili jednej kampanii od drugiej, a przy 350
 * odbiorcach i tempie 10/min wysyłka trwa ponad pół godziny i bez licznika
 * wygląda, jakby się zawiesiła.
 *
 * Wypełniane WYŁĄCZNIE przy wysyłce do klientów — próbka na własny adres nie
 * jest częścią kampanii i nie może zawyżać licznika.
 *
 * `nullOnDelete`: skasowanie szkicu nie może usunąć maili z kolejki (a szkicu
 * po wysyłce i tak nie da się skasować).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->foreignId('bulk_mailing_id')->nullable()->after('shop_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bulk_mailing_id');
        });
    }
};
