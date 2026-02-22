<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_grade_schemes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('term_id')->unique()->constrained('terms')->cascadeOnDelete();
            $table->json('weights');
            $table->enum('normalize_strategy', ['use_scheme_only', 'scheme_times_assessment_weight'])->default('scheme_times_assessment_weight');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_grade_schemes');
    }
};
