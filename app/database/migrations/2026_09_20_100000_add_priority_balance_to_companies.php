<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // constante K da prioridade da fila: score × (score + K) × valor. Nulo = padrão do sistema.
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority_balance')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('priority_balance');
        });
    }
};
