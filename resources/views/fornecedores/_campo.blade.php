{{-- Campo de texto padrão: $n (name), $rotulo, $attrs, $fornecedor --}}
@php($obrigatorio = $attrs['required'] ?? false)
<label class="form-label {{ $obrigatorio ? 'gc-required' : '' }}">{{ $rotulo }}</label>
<input type="{{ $attrs['type'] ?? 'text' }}" name="{{ $n }}" id="{{ $attrs['id'] ?? $n }}"
       value="{{ old($n, $fornecedor?->{$n} ?? '') }}"
       maxlength="{{ $attrs['maxlength'] ?? 255 }}" placeholder="{{ $attrs['placeholder'] ?? '' }}"
       class="form-control @error($n) is-invalid @enderror">
@error($n) <div class="invalid-feedback">{{ $message }}</div> @enderror
