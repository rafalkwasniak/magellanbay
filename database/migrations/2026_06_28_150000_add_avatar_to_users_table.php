<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Awatar użytkownika (profil w panelu). Ścieżka na dysku `public`
 * (storage/app/public, serwowane przez symlink public/storage). Nullable —
 * awatar jest opcjonalny; gdy go brak, pokazujemy inicjały.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
