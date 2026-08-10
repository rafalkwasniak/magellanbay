<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Powiązanie maila w outboxie z wiadomością platformy — po nim liczymy postęp
 * wysyłki, dokładnie tak jak `bulk_mailing_id` przy korespondencji sklepu.
 *
 * `cascadeOnDelete` jest bezpieczne, bo skasować da się wyłącznie SZKIC, a szkic
 * nie ma jeszcze ani jednego maila w kolejce. Wysłanej wiadomości nie usuwamy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->foreignId('platform_mailing_id')->nullable()->after('bulk_mailing_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_mailing_id');
        });
    }
};
