<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tożsamość nadawcy zamrożona na wierszu outboxu: `from_name` (display-name
 * koperty — nazwa sklepu) i `reply_to` (adres kontaktowy sklepu). Zamrażamy przy
 * kolejkowaniu, spójnie z ideą outboxu „wysłany mail zostaje, jaki był". Puste =
 * mail platformy (Kramio) — renderer użyje domyślnych z `config('mail.from')`,
 * bez Reply-To.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('from_name')->nullable()->after('to_name');
            $table->string('reply_to')->nullable()->after('from_name');
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn(['from_name', 'reply_to']);
        });
    }
};
