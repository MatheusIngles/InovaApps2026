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
        Schema::create('metric_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('label', 100);
            $table->string('direction', 10);
            $table->decimal('healthy_value', 14, 4);
            $table->decimal('critical_value', 14, 4);
            $table->decimal('weight', 6, 2);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_definitions');
    }
};
