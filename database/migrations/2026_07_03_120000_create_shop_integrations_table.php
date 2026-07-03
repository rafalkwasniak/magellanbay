<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela integracji per-sklep (kategoria 3 z plan-shop-settings-storage).
 * Jeden wiersz = jedna integracja danego sklepu (GA, w przyszłości PayU,
 * InPost itd.). `type` rozpoznaje enum IntegrationType, `config` trzyma
 * parametry zaszyfrowane APP_KEY-em (jeden mechanizm dla wszystkich, także
 * dla niesekretnego GA). Nowa integracja = nowy case enuma, bez migracji.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->boolean('enabled')->default(false);
            $table->text('config')->nullable();   // encrypted:array (cast na modelu)
            $table->timestamps();

            $table->unique(['shop_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_integrations');
    }
};
