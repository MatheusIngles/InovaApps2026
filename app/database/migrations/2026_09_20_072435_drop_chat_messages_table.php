<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** O chat não grava a conversa: a tabela nunca foi usada. */
    public function up(): void
    {
        Schema::dropIfExists('chat_messages');
    }

    public function down(): void {}
};
