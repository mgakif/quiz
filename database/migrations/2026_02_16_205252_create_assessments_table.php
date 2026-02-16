<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('term_id')->constrained('terms')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_exam_id')->unique()->index();
            $table->unsignedBigInteger('class_id')->nullable()->index();
            $table->string('title');
            $table->enum('category', ['quiz', 'exam', 'assignment', 'participation'])->default('quiz')->index();
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->decimal('max_points', 8, 2)->default(100.00);
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->boolean('published')->default(true)->index();
            $table->timestamps();

            $table->index(['term_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
