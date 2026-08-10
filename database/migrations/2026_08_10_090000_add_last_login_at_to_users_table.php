<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ślad ostatniego logowania sprzedawcy — potrzebny w konsoli admina, żeby
 * odróżnić konto żywe od porzuconego zaraz po rejestracji.
 *
 * Świadomie JEDNA kolumna, a nie historia logowań: pytanie brzmi „czy on tu w
 * ogóle wchodzi", nie „kiedy wchodził przez ostatni rok". Pełny dziennik to
 * tabela rosnąca z każdym logowaniem i dane osobowe do usuwania przy kasowaniu
 * konta — koszt bez dzisiejszego pożytku.
 *
 * UWAGA przy odczycie: wartość nalicza się dopiero OD TEJ MIGRACJI. Puste pole
 * u starego konta znaczy „nie logował się od wdrożenia", a nie „nigdy".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
