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
        Schema::table('incentive_agents', function (Blueprint $table) {
            $table->decimal('total_order_amount',16)->default(0);
            $table->integer('order_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incentive_agents', function (Blueprint $table) {
            $table->dropColumn(['total_order_amount', 'order_count']);
        });
    }
};
