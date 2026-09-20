<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('metric_definitions', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('value_type', 20)->default('decimal');
        });

        Schema::table('metric_values', function (Blueprint $table) {
            $table->decimal('value', 14, 4)->nullable()->change();
            $table->text('text_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('metric_values')->whereNull('value')->delete();

        Schema::table('metric_values', function (Blueprint $table) {
            $table->dropColumn('text_value');
            $table->decimal('value', 14, 4)->nullable(false)->change();
        });

        Schema::table('metric_definitions', function (Blueprint $table) {
            $table->dropColumn(['description', 'value_type']);
        });
    }
};
