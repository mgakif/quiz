<?php

declare(strict_types=1);

namespace App\Filament\Resources\Exams\Pages;

use App\Domain\Assessments\BindAssessmentToExam;
use App\Filament\Resources\Exams\ExamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    /**
     * @var array{term_id:string,category:string,weight:float,published:bool}
     */
    protected array $assessmentDraft = [];

    protected function mutateFormDataBeforeCreate(array $data): array
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

    protected function afterCreate(): void
    {
        $assessment = app(BindAssessmentToExam::class)->ensureBound($this->record);
        $assessment->update($this->assessmentDraft);
    }
}
