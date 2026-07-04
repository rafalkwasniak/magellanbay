<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dane kontaktowe sklepu: publiczny e-mail i telefon. Oba wymagane w panelu
 * (budują wiarygodność sklepu i zasilą Reply-To maili „od sklepu" oraz stopkę
 * storefrontu). Kolumny nullable na poziomie bazy — istniejące sklepy nie mają
 * ich jeszcze, a „wymagane" wymuszamy regułą Form Requestu. E-mail backfillujemy
 * z konta właściciela, żeby nic nie było puste; telefon zostaje do uzupełnienia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('logo_path');
            $table->string('contact_phone')->nullable()->after('contact_email');
        });

        // Backfill e-maila kontaktowego z właściciela (portable — bez JOIN-a w UPDATE).
        foreach (DB::table('shops')->whereNull('contact_email')->get(['id', 'owner_id']) as $shop) {
            $email = DB::table('users')->where('id', $shop->owner_id)->value('email');

            if ($email !== null) {
                DB::table('shops')->where('id', $shop->id)->update(['contact_email' => $email]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'contact_phone']);
        });
    }
};
