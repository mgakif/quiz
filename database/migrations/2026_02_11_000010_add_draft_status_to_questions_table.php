<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::create('questions_tmp', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->enum('status', ['draft', 'active', 'archived', 'deprecated'])->default('active')->index();
                $table->unsignedTinyInteger('difficulty')->nullable()->index();
                $table->json('tags')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            DB::statement('INSERT INTO questions_tmp (id, uuid, status, difficulty, tags, created_by, created_at, updated_at) SELECT id, uuid, status, difficulty, tags, created_by, created_at, updated_at FROM questions');
            Schema::drop('questions');
            Schema::rename('questions_tmp', 'questions');

            return;
        }

        DB::statement("ALTER TABLE questions MODIFY status ENUM('draft','active','archived','deprecated') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::create('questions_tmp', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->enum('status', ['active', 'archived', 'deprecated'])->default('active')->index();
                $table->unsignedTinyInteger('difficulty')->nullable()->index();
                $table->json('tags')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            DB::statement("INSERT INTO questions_tmp (id, uuid, status, difficulty, tags, created_by, created_at, updated_at) SELECT id, uuid, CASE WHEN status = 'draft' THEN 'active' ELSE status END, difficulty, tags, created_by, created_at, updated_at FROM questions");
            Schema::drop('questions');
            Schema::rename('questions_tmp', 'questions');

            return;
        }

        DB::statement("UPDATE questions SET status = 'active' WHERE status = 'draft'");
        DB::statement("ALTER TABLE questions MODIFY status ENUM('active','archived','deprecated') NOT NULL DEFAULT 'active'");
    }
};
