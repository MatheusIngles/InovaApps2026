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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('external_code')->unique();
            $table->string('segment');
            $table->string('size');
            $table->string('plan');
            $table->decimal('monthly_value', 12, 2);
            $table->unsignedSmallInteger('contracted_sla_hours');
            $table->date('contract_started_at');
            $table->string('status');
            $table->date('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
