<x-filament-panels::page>
    <div class="space-y-4">
        {{ $this->form }}

        <x-filament::button wire:click="applyFilters">
            Apply Filters
        </x-filament::button>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="overflow-hidden rounded-lg border border-gray-200">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-800">
                    Weakest 5
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Key</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Avg Percent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($this->weakestRows as $row)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $row['key'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $row['avg_percent'] !== null ? number_format($row['avg_percent'], 2) . '%' : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-6 text-center text-sm text-gray-500">No released attempts found for selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-800">
                    Strongest 5
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Key</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Avg Percent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($this->strongestRows as $row)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $row['key'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $row['avg_percent'] !== null ? number_format($row['avg_percent'], 2) . '%' : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-6 text-center text-sm text-gray-500">No released attempts found for selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Key</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Attempts</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Avg Score</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Avg Percent</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($this->rows as $row)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $row['key'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $row['attempts'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($row['avg_score'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $row['avg_percent'] !== null ? number_format($row['avg_percent'], 2) . '%' : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No released attempts found for selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
