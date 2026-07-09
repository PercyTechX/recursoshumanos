@props(['model'])

{{-- Recuadro para firmar con el dedo. Sincroniza con la propiedad Livewire {{ $model }}. --}}
<div wire:ignore x-data="firmaPad('{{ $model }}')">
    <div class="rounded-lg border border-line bg-white overflow-hidden">
        <canvas x-ref="canvas" class="w-full h-40 touch-none"></canvas>
    </div>
    <div class="mt-1 flex items-center justify-between">
        <span class="text-xs text-faint">Firme con el dedo dentro del recuadro</span>
        <button type="button" @click="limpiar()" class="text-xs text-muted hover:text-danger">Limpiar</button>
    </div>
</div>
