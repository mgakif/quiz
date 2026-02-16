<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Leaderboards\Services\LeaderboardService;
use App\Jobs\ComputeLeaderboardJob;
use App\Models\Attempt;
use App\Models\StudentProfile;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LeaderboardAdmin extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Leaderboard Admin';

    protected static ?string $title = 'Leaderboard Admin';

    protected string $view = 'filament.pages.leaderboard-admin';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<int, array{student_id:int,student_name:string,nickname:string,show_on_leaderboard:bool}>
     */
    public array $profileRows = [];

    /**
     * @var array<int, array{
     *     rank:int,
     *     student_id:int,
     *     nickname:string,
     *     points_total:float,
     *     max_total:float,
     *     percent:float,
     *     attempts_count:int,
     *     last_attempt_at:string|null
     * }>
     */
    public array $leaderboardEntries = [];

    public function mount(LeaderboardService $leaderboardService): void
    {
        $defaultClassId = array_key_first($this->getClassOptions());

        $this->form->fill([
            'class_id' => $defaultClassId !== null ? (int) $defaultClassId : null,
            'period' => 'all_time',
        ]);

        $this->loadState($leaderboardService);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Filters')
                    ->schema([
                        Select::make('class_id')
                            ->label('Class')
                            ->options($this->getClassOptions())
                            ->required(),
                        Select::make('period')
                            ->options([
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'all_time' => 'All Time',
                            ])
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public function applyFilters(LeaderboardService $leaderboardService): void
    {
        $this->loadState($leaderboardService);
    }

    public function saveProfiles(LeaderboardService $leaderboardService): void
    {
        $classId = (int) ($this->data['class_id'] ?? 0);

        if ($classId <= 0) {
            return;
        }

        $nicknames = collect($this->profileRows)
            ->map(fn (array $row): string => trim((string) ($row['nickname'] ?? '')))
            ->filter()
            ->values();

        if ($nicknames->count() !== $nicknames->unique()->count()) {
            throw ValidationException::withMessages([
                'profileRows' => 'Nicknames must be unique in the same class.',
            ]);
        }

        foreach ($this->profileRows as $row) {
            $studentId = (int) ($row['student_id'] ?? 0);

            if ($studentId <= 0) {
                continue;
            }

            StudentProfile::query()->updateOrCreate(
                [
                    'student_id' => $studentId,
                ],
                [
                    'class_id' => $classId,
                    'nickname' => trim((string) ($row['nickname'] ?? '')),
                    'show_on_leaderboard' => (bool) ($row['show_on_leaderboard'] ?? true),
                    'updated_at' => now(),
                ],
            );
        }

        Notification::make()
            ->success()
            ->title('Nickname settings saved.')
            ->send();

        $this->loadState($leaderboardService);
    }

    public function recomputeNow(LeaderboardService $leaderboardService): void
    {
        $classId = (int) ($this->data['class_id'] ?? 0);
        $period = (string) ($this->data['period'] ?? 'all_time');

        if ($classId <= 0) {
            return;
        }

        dispatch_sync(new ComputeLeaderboardJob($classId, $period));

        Notification::make()
            ->success()
            ->title('Leaderboard recomputed.')
            ->send();

        $this->loadState($leaderboardService);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isTeacher() ?? false;
    }

    /**
     * @return array<string, string>
     */
    private function getClassOptions(): array
    {
        return Attempt::query()
            ->select('exam_id')
            ->distinct()
            ->orderBy('exam_id')
            ->pluck('exam_id', 'exam_id')
            ->mapWithKeys(fn (int|string $value): array => [(string) $value => "Class {$value}"])
            ->all();
    }

    private function loadState(LeaderboardService $leaderboardService): void
    {
        $classId = (int) ($this->data['class_id'] ?? 0);
        $period = (string) ($this->data['period'] ?? 'all_time');

        if ($classId <= 0) {
            $this->profileRows = [];
            $this->leaderboardEntries = [];

            return;
        }

        $this->profileRows = $this->buildProfileRows($classId);
        $payload = $leaderboardService->getLeaderboard($classId, $period);
        $this->leaderboardEntries = collect($payload['entries'] ?? [])->take(20)->values()->all();
    }

    /**
     * @return array<int, array{student_id:int,student_name:string,nickname:string,show_on_leaderboard:bool}>
     */
    private function buildProfileRows(int $classId): array
    {
        $students = User::query()
            ->where('role', User::ROLE_STUDENT)
            ->whereIn('id', Attempt::query()->where('exam_id', $classId)->select('student_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);

        /** @var Collection<int, StudentProfile> $profiles */
        $profiles = StudentProfile::query()
            ->where('class_id', $classId)
            ->get()
            ->keyBy('student_id');

        return $students
            ->map(function (User $student) use ($profiles): array {
                $profile = $profiles->get($student->id);

                return [
                    'student_id' => (int) $student->id,
                    'student_name' => (string) $student->name,
                    'nickname' => (string) ($profile?->nickname ?? ''),
                    'show_on_leaderboard' => (bool) ($profile?->show_on_leaderboard ?? true),
                ];
            })
            ->values()
            ->all();
    }
}
