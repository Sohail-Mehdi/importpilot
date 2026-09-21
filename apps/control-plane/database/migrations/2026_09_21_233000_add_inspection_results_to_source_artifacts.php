<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_artifacts', function (Blueprint $table) {
            $table->string('parquet_key')->nullable();
            $table->json('inspection_profile')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('source_artifacts', function (Blueprint $table) {
            $table->dropColumn(['parquet_key', 'inspection_profile']);
        });
    }
};
