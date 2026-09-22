@props(['value'])

<span {{ $attributes->merge(['class' => 'badge-situacao ' . ($value ? 'badge-ativo' : 'badge-inativo')]) }}>
    {{ $value ? 'Ativo' : 'Inativo' }}
</span>
