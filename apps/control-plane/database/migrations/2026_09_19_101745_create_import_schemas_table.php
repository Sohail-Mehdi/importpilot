<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_schemas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('schema_name');
            $table->jsonb('definition');
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->unique(['project_id', 'schema_name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_schemas');
    }
};
