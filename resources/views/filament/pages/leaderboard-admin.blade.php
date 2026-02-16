<x-filament-panels::page>
    <div class="space-y-4">
        {{ $this->form }}

        <div class="flex gap-2">
            <x-filament::button wire:click="applyFilters">
                Apply Filters
            </x-filament::button>
            <x-filament::button color="gray" wire:click="saveProfiles">
                Save Nicknames
            </x-filament::button>
            <x-filament::button color="success" wire:click="recomputeNow">
                Recompute Now
            </x-filament::button>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-800">
                Nickname Settings
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Student</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Nickname</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Show</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($this->profileRows as $index => $row)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $row['student_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <input
                                    type="text"
                                    wire:model.defer="profileRows.{{ $index }}.nickname"
                                    class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm"
                                />
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <input
                                    type="checkbox"
                                    wire:model.defer="profileRows.{{ $index }}.show_on_leaderboard"
                                    class="h-4 w-4 rounded border-gray-300"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">No students found for selected class.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-800">
                Current Top 20
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Rank</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Nickname</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Percent</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Attempts</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($this->leaderboardEntries as $entry)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $entry['rank'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $entry['nickname'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($entry['percent'], 2) }}%</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $entry['attempts_count'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No leaderboard data available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
