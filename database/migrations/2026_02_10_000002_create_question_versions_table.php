<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('type');
            $table->json('payload');
            $table->json('answer_key');
            $table->json('rubric')->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'version']);
            $table->index(['question_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_versions');
    }
};
