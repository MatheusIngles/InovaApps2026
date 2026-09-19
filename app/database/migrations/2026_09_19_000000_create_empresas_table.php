<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 10)->unique();
            $t->string('nome');
            $t->string('segmento');
            $t->string('porte');
            $t->string('plano');
            $t->unsignedInteger('valor');
            $t->unsignedSmallInteger('sla_h');
            $t->date('inicio');
            $t->string('status')->index();          // Ativo | Cancelado
            $t->string('mes_cancel', 7)->nullable();
            $t->unsignedTinyInteger('score');       // 0-100
            $t->string('nivel');                    // Crítico | Alto | Médio | Baixo
            $t->unsignedInteger('exposicao');       // score × valor
            $t->json('sinais');
            $t->json('similares');
            $t->json('hist');
            $t->json('nps');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
