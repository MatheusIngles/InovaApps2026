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
        Schema::create('customer_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('reference_month');
            $table->unsignedSmallInteger('tickets_opened');
            $table->unsignedSmallInteger('tickets_critical');
            $table->unsignedSmallInteger('tickets_reopened');
            $table->unsignedSmallInteger('tickets_within_sla');
            $table->decimal('sla_percentage', 5, 2)->nullable();
            $table->decimal('avg_resolution_hours', 8, 2);
            $table->unsignedSmallInteger('formal_complaints');
            $table->decimal('platform_usage_percentage', 5, 2);
            $table->unsignedSmallInteger('payment_delay_days');
            $table->unsignedSmallInteger('meetings_expected');
            $table->unsignedSmallInteger('meetings_completed');
            $table->timestamps();
            $table->unique(['customer_id', 'reference_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_metrics');
    }
};
