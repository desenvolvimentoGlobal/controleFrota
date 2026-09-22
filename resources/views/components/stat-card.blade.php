@props(['icon' => 'bi-graph-up', 'label' => '', 'value' => '', 'tone' => 'teal', 'href' => null])

@php
    $toneClass = match($tone) {
        'amber' => 'bg-soft-amber',
        'red'   => 'bg-soft-red',
        'green' => 'bg-soft-green',
        'navy'  => 'bg-soft-navy',
        'blue'  => 'bg-soft-blue',
        default => 'bg-soft-teal',
    };
@endphp

<div class="card gc-stat-card h-100">
    <div class="card-body d-flex align-items-start justify-content-between">
        <div>
            <div class="gc-stat-label">{{ $label }}</div>
            <div class="gc-stat-value">{{ $value }}</div>
            @if($href)
                <a href="{{ $href }}" class="small">Ver detalhes <i class="bi bi-arrow-right"></i></a>
            @endif
        </div>
        <div class="gc-stat-icon {{ $toneClass }}">
            <i class="bi {{ $icon }}"></i>
        </div>
    </div>
</div>
