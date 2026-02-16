<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('student_profiles')) {
            return;
        }

        Schema::create('student_profiles_tmp', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('class_id')->index();
            $table->string('nickname');
            $table->boolean('show_on_leaderboard')->default(true)->index();
            $table->timestamps();

            $table->unique(['class_id', 'nickname']);
        });

        DB::statement('
            INSERT INTO student_profiles_tmp (student_id, class_id, nickname, show_on_leaderboard, created_at, updated_at)
            SELECT sp.student_id, sp.class_id, sp.nickname, sp.show_on_leaderboard, sp.created_at, sp.updated_at
            FROM student_profiles sp
            INNER JOIN (
                SELECT student_id, MAX(id) AS latest_id
                FROM student_profiles
                GROUP BY student_id
            ) latest ON latest.latest_id = sp.id
        ');

        Schema::drop('student_profiles');
        Schema::rename('student_profiles_tmp', 'student_profiles');
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_profiles')) {
            return;
        }

        Schema::create('student_profiles_tmp', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('class_id')->index();
            $table->string('nickname');
            $table->boolean('show_on_leaderboard')->default(true)->index();
            $table->timestamps();

            $table->unique(['class_id', 'nickname']);
            $table->unique(['class_id', 'student_id']);
        });

        DB::statement('
            INSERT INTO student_profiles_tmp (student_id, class_id, nickname, show_on_leaderboard, created_at, updated_at)
            SELECT student_id, class_id, nickname, show_on_leaderboard, created_at, updated_at
            FROM student_profiles
        ');

        Schema::drop('student_profiles');
        Schema::rename('student_profiles_tmp', 'student_profiles');
    }
};
