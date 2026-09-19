<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('source_artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_session_id')->unique()->constrained('import_sessions')->cascadeOnDelete(); // one artifact per session
            $table->string('storage_key')->unique();
            $table->string('original_filename');
            $table->unsignedBigInteger('file_size');
            $table->string('content_type');
            $table->string('checksum')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('source_artifacts');
    }
};
