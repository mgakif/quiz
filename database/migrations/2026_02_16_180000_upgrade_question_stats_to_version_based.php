<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('question_stats')) {
            return;
        }

        Schema::create('question_stats_tmp', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->nullable()->constrained('questions')->cascadeOnDelete();
            $table->foreignId('question_version_id')->nullable()->unique()->constrained('question_versions')->cascadeOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('incorrect_count')->default(0);
            $table->decimal('correct_rate', 5, 2)->nullable();
            $table->decimal('avg_score', 8, 2)->nullable();
            $table->unsignedInteger('appeal_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('question_id');
        });

        DB::statement('
            INSERT INTO question_stats_tmp (
                id, question_id, question_version_id, usage_count, correct_count, incorrect_count, correct_rate, avg_score, appeal_count, last_used_at, created_at, updated_at
            )
            SELECT
                id,
                question_id,
                NULL as question_version_id,
                usage_count,
                0 as correct_count,
                0 as incorrect_count,
                correct_rate,
                NULL as avg_score,
                appeal_count,
                last_used_at,
                created_at,
                updated_at
            FROM question_stats
        ');

        Schema::drop('question_stats');
        Schema::rename('question_stats_tmp', 'question_stats');
    }

    public function down(): void
    {
        if (! Schema::hasTable('question_stats')) {
            return;
        }

        Schema::create('question_stats_tmp', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->unique()->constrained('questions')->cascadeOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->decimal('correct_rate', 5, 2)->nullable();
            $table->unsignedInteger('appeal_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        DB::statement('
            INSERT INTO question_stats_tmp (question_id, usage_count, correct_rate, appeal_count, last_used_at, created_at, updated_at)
            SELECT
                question_id,
                COALESCE(SUM(usage_count), 0) as usage_count,
                CASE
                    WHEN COALESCE(SUM(correct_count + incorrect_count), 0) > 0
                        THEN ROUND((SUM(correct_count) * 100.0) / SUM(correct_count + incorrect_count), 2)
                    ELSE MAX(correct_rate)
                END as correct_rate,
                COALESCE(SUM(appeal_count), 0) as appeal_count,
                MAX(last_used_at) as last_used_at,
                MIN(created_at) as created_at,
                MAX(updated_at) as updated_at
            FROM question_stats
            WHERE question_id IS NOT NULL
            GROUP BY question_id
        ');

        Schema::drop('question_stats');
        Schema::rename('question_stats_tmp', 'question_stats');
    }
};
