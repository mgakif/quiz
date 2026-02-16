<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Start Generation
        </x-filament::button>
    </form>
</x-filament-panels::page>
