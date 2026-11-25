<?php

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
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('expense_type_id')->constrained()->restrictOnDelete();
        });

        DB::table('expenses')
            ->whereNotNull('subcategory_id')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $expense): void {
                $categoryId = DB::table('subcategories')
                    ->where('id', $expense->subcategory_id)
                    ->value('category_id');

                DB::table('expenses')
                    ->where('id', $expense->id)
                    ->update(['category_id' => $categoryId]);
            });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['subcategory_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('subcategory_id')->nullable()->change();
            $table->foreign('subcategory_id')->references('id')->on('subcategories')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['subcategory_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('subcategory_id')->nullable(false)->change();
            $table->foreign('subcategory_id')->references('id')->on('subcategories')->restrictOnDelete();
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
