<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table): void {
            $table->dropForeign(['student_id']);
            $table->foreignId('student_id')->nullable()->change();
            $table->foreign('student_id')->references('id')->on('users')->nullOnDelete();

            $table->foreignUuid('guest_id')->nullable()->after('student_id')->constrained('guest_users')->nullOnDelete();
            $table->foreignUuid('public_exam_link_id')->nullable()->after('guest_id')->constrained('public_exam_links')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE attempts ADD CONSTRAINT attempts_student_or_guest_xor CHECK ((student_id IS NOT NULL AND guest_id IS NULL) OR (student_id IS NULL AND guest_id IS NOT NULL))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE attempts DROP CONSTRAINT IF EXISTS attempts_student_or_guest_xor');
        }

        Schema::table('attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('public_exam_link_id');
            $table->dropConstrainedForeignId('guest_id');

            $table->dropForeign(['student_id']);
            $table->foreignId('student_id')->nullable(false)->change();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
