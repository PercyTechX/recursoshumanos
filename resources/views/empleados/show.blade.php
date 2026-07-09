<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-navy leading-tight">Expediente del empleado</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <a href="{{ route('empleados.index') }}" wire:navigate class="text-sm text-primary hover:underline">← Volver a empleados</a>
            <div class="mt-3">
                <livewire:empleados.expediente :empleado="$empleado" />
            </div>
        </div>
    </div>
</x-app-layout>
