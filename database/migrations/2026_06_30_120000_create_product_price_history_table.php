<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historia cen produktu — fundament obowiązku informacyjnego Omnibus
 * („najniższa cena z 30 dni przed obniżką"). Jeden wiersz = cena brutto
 * obowiązująca od `recorded_at`. Wpisy dodaje ProductObserver przy utworzeniu
 * produktu i przy każdej zmianie `price_gross`; historii nie da się odtworzyć
 * wstecz, więc zbieramy ją od pierwszego dnia, niezależnie od storefrontu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_gross', 10, 2);
            $table->timestamp('recorded_at');

            $table->index(['product_id', 'recorded_at']);
        });

        // Zaszczepienie istniejących produktów ceną początkową (od momentu
        // ich utworzenia), żeby nie były „ślepe" w oknie 30 dni.
        $now = now();
        $rows = DB::table('products')
            ->select('id', 'price_gross', 'created_at')
            ->get()
            ->map(fn ($product) => [
                'product_id' => $product->id,
                'price_gross' => $product->price_gross,
                'recorded_at' => $product->created_at ?? $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('product_price_history')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_history');
    }
};
