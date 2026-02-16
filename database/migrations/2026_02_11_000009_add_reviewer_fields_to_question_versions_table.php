<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('question_versions', function (Blueprint $table): void {
            $table->enum('reviewer_status', ['pending', 'pass', 'fail'])->default('pending')->after('rubric')->index();
            $table->json('reviewer_issues')->nullable()->after('reviewer_status');
            $table->text('reviewer_summary')->nullable()->after('reviewer_issues');
            $table->foreignId('reviewer_override_by')->nullable()->after('reviewer_summary')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewer_overridden_at')->nullable()->after('reviewer_override_by');
            $table->text('reviewer_override_note')->nullable()->after('reviewer_overridden_at');
        });
    }

    public function down(): void
    {
        Schema::table('question_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewer_override_by');
            $table->dropColumn([
                'reviewer_status',
                'reviewer_issues',
                'reviewer_summary',
                'reviewer_overridden_at',
                'reviewer_override_note',
            ]);
        });
    }
};
