<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_gradings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_item_id')->unique()->constrained('attempt_items')->cascadeOnDelete();
            $table->json('response_json');
            $table->decimal('confidence', 5, 4)->default(0);
            $table->json('flags')->nullable();
            $table->enum('status', ['draft', 'needs_review', 'approved', 'rejected'])->default('draft')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_gradings');
    }
};
