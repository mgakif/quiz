<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attempt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('attempts')->cascadeOnDelete();
            $table->foreignId('question_version_id')->constrained('question_versions')->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->decimal('max_points', 8, 2);
            $table->timestamps();

            $table->index(['attempt_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_items');
    }
};
