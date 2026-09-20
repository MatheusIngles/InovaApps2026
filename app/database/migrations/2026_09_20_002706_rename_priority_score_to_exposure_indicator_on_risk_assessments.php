<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `priority_score` guardava risco × valor (a exposição), e não a prioridade da fila, que depende do K da empresa
 * e é calculada na hora (Customer::ordenar). O nome novo diz o que o campo é.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->renameColumn('priority_score', 'exposure_indicator');
        });
    }

    public function down(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->renameColumn('exposure_indicator', 'priority_score');
        });
    }
};
