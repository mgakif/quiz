<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionStat;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::factory()->create([
            'name' => 'Teacher One',
            'email' => 'teacher@example.com',
            'role' => User::ROLE_TEACHER,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $question = Question::factory()->create([
                'created_by' => $teacher->id,
                'difficulty' => $i,
                'tags' => ['topic-' . $i, 'core'],
            ]);

            $question->createVersion([
                'type' => 'mcq',
                'payload' => [
                    'text' => "Question {$i} v1",
                    'options' => ['A' => '10', 'B' => '20', 'C' => '30', 'D' => '40'],
                ],
                'answer_key' => ['correct' => 'A'],
                'rubric' => null,
            ]);

            $question->createVersion([
                'type' => 'mcq',
                'payload' => [
                    'text' => "Question {$i} v2",
                    'options' => ['A' => '11', 'B' => '21', 'C' => '31', 'D' => '41'],
                ],
                'answer_key' => ['correct' => 'B'],
                'rubric' => null,
            ]);

            QuestionStat::query()->create([
                'question_id' => $question->id,
                'usage_count' => 0,
                'correct_rate' => null,
                'appeal_count' => 0,
                'last_used_at' => null,
            ]);
        }
    }
}
