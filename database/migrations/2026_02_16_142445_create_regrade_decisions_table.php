<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('regrade_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('scope', ['attempt_item', 'question_version'])->index();
            $table->foreignId('attempt_item_id')->nullable()->constrained('attempt_items')->nullOnDelete();
            $table->foreignId('question_version_id')->nullable()->constrained('question_versions')->nullOnDelete();
            $table->enum('decision_type', ['answer_key_change', 'rubric_change', 'partial_credit', 'void_question'])->index();
            $table->json('payload');
            $table->foreignId('decided_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('decided_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regrade_decisions');
    }
};
