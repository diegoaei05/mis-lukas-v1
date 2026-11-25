<?php

use App\Enums\CategoryColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('color')->nullable()->after('name');
        });

        foreach (['Comida', 'Otros', 'Vivienda', 'Financieros', 'PKYTP'] as $name) {
            $color = CategoryColor::forCatalogName($name);

            if ($color === null) {
                continue;
            }

            DB::table('categories')->where('name', $name)->update([
                'color' => $color->value,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
