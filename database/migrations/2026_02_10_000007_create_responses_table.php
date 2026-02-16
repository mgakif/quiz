<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_item_id')->unique()->constrained('attempt_items')->cascadeOnDelete();
            $table->json('response_payload');
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};
