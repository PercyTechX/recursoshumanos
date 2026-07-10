@props(['name', 'class' => 'w-5 h-5'])

@php
    // Íconos SVG (trazo, estilo Heroicons/Lucide — MIT). Usan currentColor.
    $paths = [
        'home' => '<path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.2a3.2 3.2 0 0 1 0 6"/><path d="M17.5 14.2A5.5 5.5 0 0 1 20.5 19"/>',
        'document' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h6"/>',
        'clipboard' => '<rect x="8" y="4" width="8" height="4" rx="1"/><path d="M9 4H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><path d="M9 12h6"/><path d="M9 16h4"/>',
        'wrench' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.2L4 16.8 7.2 20l5.3-5.3a4 4 0 0 0 5.2-5.4l-2.5 2.5-2.3-2.3z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'cash' => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9v6M18 9v6"/>',
        'pencil' => '<path d="M15.5 5.5 18.5 8.5"/><path d="M4 20l4-1 10-10-3-3L5 16z"/>',
        'trash' => '<path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"/><path d="M10 11v6M14 11v6"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 4v4h4"/><path d="M12 8v4l3 2"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'download' => '<path d="M12 3v12"/><path d="M8 11l4 4 4-4"/><path d="M4 21h16"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'whatsapp' => '<path d="M12 3a9 9 0 0 0-7.7 13.6L3 21l4.5-1.2A9 9 0 1 0 12 3z"/><path d="M8.5 8.2c-.2 0-.5.1-.7.4-.2.3-.8.9-.8 2.1 0 1.2.9 2.4 1 2.6.1.2 1.7 2.8 4.3 3.8 2.1.8 2.6.7 3 .6.5-.1 1.4-.6 1.6-1.2.2-.6.2-1 .1-1.2 0-.1-.3-.2-.6-.4-.3-.2-1.4-.7-1.7-.8-.2-.1-.4-.1-.6.1-.2.3-.6.8-.8.9-.1.2-.3.2-.5.1-.3-.2-1.1-.4-2-1.3-.7-.6-1.2-1.4-1.3-1.7-.1-.2 0-.4.1-.5l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5 0-.1-.6-1.5-.8-2-.2-.5-.4-.4-.6-.4z"/>',
        'bell' => '<path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'logout' => '<path d="M14 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-2"/><path d="M18 12H9"/><path d="M15 9l3 3-3 3"/>',
        'check' => '<path d="M5 12l5 5L20 7"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'return' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
    ];
    $svg = $paths[$name] ?? '';
@endphp

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! $svg !!}
</svg>
