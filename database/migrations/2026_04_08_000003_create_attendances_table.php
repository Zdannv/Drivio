<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('delivery_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['check_in', 'check_out', 'proof_of_delivery'])->default('check_in');
            $table->string('photo_path')->nullable()->comment('Live selfie taken in the field');
            $table->double('face_similarity_score')->nullable()->comment('AI face match score 0.0-1.0');
            $table->enum('validation_status', ['valid', 'invalid'])->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};