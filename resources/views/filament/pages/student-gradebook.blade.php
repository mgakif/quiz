<x-filament-panels::page>
    <div class="space-y-4">
        <form wire:submit="applyFilters" class="space-y-4">
            {{ $this->form }}

            <div class="flex gap-2">
                <x-filament::button type="submit">Load Gradebook</x-filament::button>
                <x-filament::button type="button" color="success" wire:click="saveOverride">Save Override</x-filament::button>
            </div>
        </form>

        @if($result)
            <x-filament::section heading="Summary">
                <div class="grid grid-cols-1 gap-2 text-sm">
                    <div><strong>Computed Grade:</strong> {{ $result['computed_grade'] ?? '-' }}</div>
                    <div><strong>Missing Assessments:</strong> {{ $result['missing_assessments_count'] ?? 0 }}</div>
                </div>
            </x-filament::section>

            <x-filament::section heading="Assessments">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="py-2 text-left">Title</th>
                                <th class="py-2 text-left">Category</th>
                                <th class="py-2 text-left">Weight</th>
                                <th class="py-2 text-left">Status</th>
                                <th class="py-2 text-left">Percent</th>
                                <th class="py-2 text-left">Released At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assessmentRows as $row)
                                <tr class="border-b">
                                    <td class="py-2">{{ $row['title'] }}</td>
                                    <td class="py-2">{{ $row['category'] }}</td>
                                    <td class="py-2">{{ $row['weight'] }}</td>
                                    <td class="py-2">{{ $row['attempt_status'] }}</td>
                                    <td class="py-2">{{ $row['percent'] ?? '-' }}</td>
                                    <td class="py-2">{{ $row['released_at'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
