<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leaderboards', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('class_id')->index();
            $table->enum('period', ['weekly', 'monthly', 'all_time'])->index();
            $table->timestamp('computed_at')->index();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['class_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboards');
    }
};
