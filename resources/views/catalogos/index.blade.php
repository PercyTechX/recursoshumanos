<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-navy leading-tight">Catálogos del sistema</h2>
    </x-slot>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <livewire:catalogos.tabla />
        </div>
    </div>
</x-app-layout>
