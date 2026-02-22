<x-filament-panels::page>
    <div class="space-y-4">
        <form wire:submit="applyFilters" class="space-y-4">
            {{ $this->form }}

            <div class="flex gap-2">
                <x-filament::button type="submit">Apply Filters</x-filament::button>
                <x-filament::button type="button" color="success" wire:click="saveGradeScheme">Save Grade Scheme</x-filament::button>
                <x-filament::button type="button" color="warning" wire:click="recompute">Recompute</x-filament::button>
            </div>

            <div class="text-sm text-gray-600">
                Grade scheme total: {{ number_format($this->gradeSchemeTotal, 2) }} (target 1.00, auto-normalized in compute)
            </div>
        </form>

        <x-filament::section heading="Students">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="py-2 text-left">Student</th>
                            <th class="py-2 text-left">Computed</th>
                            <th class="py-2 text-left">Overridden</th>
                            <th class="py-2 text-left">Final</th>
                            <th class="py-2 text-left">Missing</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="border-b">
                                <td class="py-2">{{ $row['student_name'] }}</td>
                                <td class="py-2">{{ $row['computed_grade'] ?? '-' }}</td>
                                <td class="py-2">{{ $row['overridden_grade'] ?? '-' }}</td>
                                <td class="py-2">{{ $row['final_grade'] ?? '-' }}</td>
                                <td class="py-2">{{ $row['missing_assessments_count'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-3" colspan="5">No rows</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
