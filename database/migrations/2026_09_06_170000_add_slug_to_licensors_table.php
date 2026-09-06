<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adres publicznej strony partnera.
 *
 * Wprost ze specyfikacji: „musi być także ekran prezentujący wszystkie produkty
 * tylko wybranej firmy, np. Stowarzyszenia Tychy Razem". Organizator biegu ma
 * dostać jeden link, który wyśle swoim uczestnikom — a link musi przeżyć zmianę
 * nazwy w kartotece, więc `slug` liczymy RAZ, przy utworzeniu.
 *
 * ---------------------------------------------------------------------------
 * CO STAJE SIĘ PUBLICZNE, A CO ZOSTAJE W KARTOTECE
 *
 * Publiczna jest NAZWA i lista produktów — i tak widnieją na magnesie, więc
 * niczego nie ujawniają. W kartotece zostaje wszystko, co dotyczy UMOWY:
 * osoba kontaktowa, e-mail, numer porozumienia, notatki i stawki. Kupujący
 * widzi opłatę w rozbiciu ceny, ale nie ma powodu znać warunków, na jakich
 * sklep ją pobiera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licensors', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Istniejące wpisy dostają slug z nazwy. Kolizję rozstrzyga
        // identyfikator — dwóch partnerów o tej samej nazwie to rzadkość,
        // ale sufiks jest tańszy niż awaria unikalnego indeksu przy migracji.
        foreach (DB::table('licensors')->get(['id', 'shop_id', 'name']) as $row) {
            $base = Str::slug($row->name) ?: 'partner';
            $slug = $base;

            $zajety = DB::table('licensors')
                ->where('shop_id', $row->shop_id)
                ->where('slug', $slug)
                ->exists();

            if ($zajety) {
                $slug = $base.'-'.$row->id;
            }

            DB::table('licensors')->where('id', $row->id)->update(['slug' => $slug]);
        }

        Schema::table('licensors', function (Blueprint $table) {
            $table->unique(['shop_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('licensors', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
