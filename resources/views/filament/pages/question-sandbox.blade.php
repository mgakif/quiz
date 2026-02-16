<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="submitPreview" class="space-y-4">
            {{ $this->form }}

            <div class="flex flex-wrap gap-2">
                <x-filament::button type="submit">
                    Submit Preview
                </x-filament::button>

                <x-filament::button type="button" color="gray" wire:click="evaluateRubricPreview">
                    Evaluate Rubric Preview
                </x-filament::button>

                @if ($this->canShowAiSuggestionButton())
                    <x-filament::button type="button" color="info" wire:click="toggleAiSuggestion">
                        {{ $showAiSuggestion ? 'Hide AI Suggestion' : 'Show AI Suggestion' }}
                    </x-filament::button>
                @endif
            </div>
        </form>

        @if ($previewResult)
            <x-filament::section heading="Preview Result">
                <dl class="grid grid-cols-1 gap-2 text-sm">
                    <div><strong>Feedback:</strong> {{ $previewResult['feedback'] ?? '-' }}</div>
                    <div><strong>Earned:</strong> {{ $previewResult['earned_points'] ?? 0 }}</div>
                    <div><strong>Max:</strong> {{ $previewResult['max_points'] ?? 0 }}</div>
                    <div><strong>Correct:</strong> {{ isset($previewResult['is_correct']) ? ($previewResult['is_correct'] ? 'yes' : 'no') : '-' }}</div>
                </dl>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
