@props(['icon' => 'bi-inbox', 'title' => 'Nada por aqui', 'description' => null])

<div class="gc-empty-state">
    <i class="bi {{ $icon }}"></i>
    <p class="mb-0 fw-semibold text-body">{{ $title }}</p>
    @if($description)
        <p class="mb-0 small">{{ $description }}</p>
    @endif
</div>
