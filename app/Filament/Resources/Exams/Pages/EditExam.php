<?php

declare(strict_types=1);

namespace App\Filament\Resources\Exams\Pages;

use App\Domain\Assessments\BindAssessmentToExam;
use App\Filament\Resources\Exams\ExamResource;
use App\Models\Term;
use Filament\Resources\Pages\EditRecord;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    /**
     * @var array{term_id:string,category:string,weight:float,published:bool}
     */
    protected array $assessmentDraft = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $assessment = $this->record->assessment;

        $defaultTerm = Term::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first()
            ?? Term::query()->orderByDesc('start_date')->first();

        $data['term_id'] = (string) ($assessment?->term_id ?? $defaultTerm?->id);
        $data['category'] = (string) ($assessment?->category ?? 'quiz');
        $data['weight'] = (float) ($assessment?->weight ?? 1.00);
        $data['published'] = (bool) ($assessment?->published ?? true);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->assessmentDraft = [
            'term_id' => (string) $data['term_id'],
            'category' => (string) $data['category'],
            'weight' => (float) $data['weight'],
            'published' => (bool) $data['published'],
        ];

        unset($data['term_id'], $data['category'], $data['weight'], $data['published']);

        return $data;
    }

    protected function afterSave(): void
    {
        $assessment = app(BindAssessmentToExam::class)->ensureBound($this->record);
        $assessment->update($this->assessmentDraft);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
