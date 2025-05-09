<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('old_products', function (Blueprint $table) {
            $table->id();
            $table->integer('idCategory');
            $table->text('code');
            $table->text('description');
            $table->text('description2');
            $table->text('unit');
            $table->text('image');
            $table->decimal('stock', 10, 2);
            $table->decimal('buyingPrice', 10, 2);
            $table->decimal('sellingPrice', 10, 2);
            $table->decimal('sales', 10, 2);
            $table->timestamp('date')->useCurrent()->useCurrentOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('old_products');
    }
};
