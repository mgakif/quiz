<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_generations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('status', ['pending', 'success', 'needs_review'])->default('pending')->index();
            $table->string('model')->nullable();
            $table->unsignedInteger('generated_count')->default(0);
            $table->json('blueprint');
            $table->longText('raw_output')->nullable();
            $table->json('validation_errors')->nullable();
            $table->text('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_generations');
    }
};
