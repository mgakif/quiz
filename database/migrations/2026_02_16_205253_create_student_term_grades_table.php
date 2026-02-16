<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_term_grades', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('term_id')->constrained('terms')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('computed_grade', 6, 2)->nullable();
            $table->decimal('overridden_grade', 6, 2)->nullable();
            $table->text('override_reason')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamp('overridden_at')->nullable();
            $table->timestamps();

            $table->unique(['term_id', 'student_id']);
            $table->index(['student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_term_grades');
    }
};
