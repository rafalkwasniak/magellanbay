<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Użytkownicy platformy to administratorzy i sprzedawcy. `name` trzyma imię
// (domyślka Laravela), `surname` nazwisko. Telefon i nazwisko są tu nullable —
// wymusza je walidacja na tym kroku, który ich potrzebuje (np. aktywacja sklepu).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('surname')->nullable()->after('name');
            $table->string('phone')->nullable()->after('surname');
            $table->string('role')->default('seller')->after('phone')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['surname', 'phone', 'role']);
        });
    }
};
