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
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('reference_month');
            $table->decimal('risk_probability', 7, 6);
            $table->decimal('priority_score', 14, 2)->nullable();
            $table->decimal('expected_revenue_at_risk', 14, 2);
            $table->string('confidence')->nullable();
            $table->json('signals_json')->nullable();
            $table->json('recommended_action_json')->nullable();
            $table->string('model_version');
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->unique(['customer_id', 'reference_month', 'model_version'], 'risk_assessments_customer_month_model_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
