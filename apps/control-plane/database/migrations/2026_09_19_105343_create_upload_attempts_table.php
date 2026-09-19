<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('upload_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_session_id')->constrained('import_sessions')->cascadeOnDelete();
            $table->string('storage_key')->unique();
            $table->string('status')->default('PENDING'); // PENDING, VERIFIED, REJECTED, EXPIRED
            $table->string('original_filename')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('upload_attempts');
    }
};
