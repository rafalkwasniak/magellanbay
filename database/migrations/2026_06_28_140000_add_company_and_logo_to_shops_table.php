<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dane firmowe sklepu (nazwa firmy + NIP — używane m.in. w dokumentach
 * sprzedażowych i przyszłej integracji księgowej) oraz logo sklepu.
 *
 * Wszystko nullable: dane firmowe są opcjonalne (sprzedawcą bywa osoba
 * prywatna), a logo dochodzi później. `logo_path` trzyma ścieżkę na dysku
 * `public` (storage/app/public, serwowane przez symlink public/storage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('description');
            $table->string('nip', 10)->nullable()->after('company_name');
            $table->string('logo_path')->nullable()->after('nip');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'nip', 'logo_path']);
        });
    }
};
