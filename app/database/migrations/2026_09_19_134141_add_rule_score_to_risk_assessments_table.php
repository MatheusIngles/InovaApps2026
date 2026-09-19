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
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->decimal('risk_probability', 7, 6)->nullable()->change();
            $table->decimal('expected_revenue_at_risk', 14, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropColumn('health_score');
            $table->decimal('risk_probability', 7, 6)->nullable(false)->change();
            $table->decimal('expected_revenue_at_risk', 14, 2)->nullable(false)->change();
        });
    }
};
