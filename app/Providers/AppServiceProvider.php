<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Assessments\BindAssessmentToExam;
use App\Models\Exam;
use App\Models\PublicExamLink;
use App\Models\Question;
use App\Policies\ExamPolicy;
use App\Policies\PublicExamLinkPolicy;
use App\Policies\QuestionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Exam::created(function (Exam $exam): void {
            app(BindAssessmentToExam::class)->ensureBound($exam);
        });

        Exam::updated(function (Exam $exam): void {
            app(BindAssessmentToExam::class)->syncAfterExamUpdate($exam);
        });

        Exam::deleted(function (Exam $exam): void {
            app(BindAssessmentToExam::class)->markUnpublishedOnExamDelete($exam);
        });

        Gate::policy(Exam::class, ExamPolicy::class);
        Gate::policy(PublicExamLink::class, PublicExamLinkPolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);
    }
}
