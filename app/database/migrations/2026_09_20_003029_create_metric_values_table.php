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
        Schema::create('metric_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('metric_definition_id')->constrained()->cascadeOnDelete();
            $table->date('reference_month');
            $table->decimal('value', 14, 4);
            $table->timestamps();
            $table->unique(['customer_id', 'metric_definition_id', 'reference_month'], 'metric_values_customer_definition_month_unique');
            $table->index(['company_id', 'reference_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_values');
    }
};
