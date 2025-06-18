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
        Schema::table('employees', function (Blueprint $table) {
            // First drop the unique index if it exists
            $table->dropUnique(['email']);
            // Then make the column nullable
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // First remove nullable
            $table->string('email')->nullable(false)->change();
            // Then add back the unique constraint
            $table->unique('email');
        });
    }
};
