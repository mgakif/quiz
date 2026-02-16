<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rubric_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_item_id')->unique()->constrained('attempt_items')->cascadeOnDelete();
            $table->json('scores');
            $table->decimal('total_points', 8, 2);
            $table->foreignId('graded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->text('override_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_scores');
    }
};
