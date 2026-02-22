<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_exam_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('exam_id')->index();
            $table->string('token', 128)->unique();
            $table->boolean('is_enabled')->default(true)->index();
            $table->unsignedInteger('max_attempts')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('require_name')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_exam_links');
    }
};
