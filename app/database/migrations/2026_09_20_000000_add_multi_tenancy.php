<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();           // identificador do tenant (subdomínio)
            $t->json('theme')->nullable();          // primary, secondary, font, logo
            $t->json('metric_weights')->nullable(); // lista ordenada [{k, peso}]
            $t->json('level_thresholds')->nullable();
            $t->json('chat_settings')->nullable();
            $t->json('column_mapping')->nullable();
            $t->timestamp('imported_at')->nullable();
            $t->timestamps();
        });

        Schema::table('users', fn (Blueprint $t) => $t->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete());

        Schema::table('customers', fn (Blueprint $t) => $t->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete());
        Schema::table('customers', function (Blueprint $t) {
            $t->dropUnique(['external_code']);
            $t->unique(['company_id', 'external_code']); // o mesmo código pode existir em empresas diferentes
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('customer_code')->nullable(); // empresa em foco na conversa (null = carteira)
            $t->string('role');                      // user | assistant
            $t->text('content');
            $t->string('provider')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'user_id', 'customer_code']);
        });

        // Bases existentes passam a pertencer a uma empresa padrão
        if (DB::table('customers')->exists() || DB::table('users')->exists()) {
            $id = DB::table('companies')->insertGetId(['name' => 'Empresa Demo', 'slug' => 'demo', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('customers')->update(['company_id' => $id]);
            DB::table('users')->update(['company_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::table('customers', function (Blueprint $t) {
            $t->dropUnique(['company_id', 'external_code']);
            $t->dropConstrainedForeignId('company_id');
        });
        Schema::table('customers', fn (Blueprint $t) => $t->unique('external_code'));
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('company_id'));
        Schema::dropIfExists('companies');
    }
};
