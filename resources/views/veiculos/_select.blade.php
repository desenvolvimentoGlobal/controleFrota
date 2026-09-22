{{-- Select de característica do veículo: $campo, $opcoes (valor => rótulo), $vazio, $veiculo --}}
<select name="{{ $campo }}" class="form-select @error($campo) is-invalid @enderror">
    @if($vazio !== null)
        <option value="">{{ $vazio }}</option>
    @endif
    @foreach($opcoes as $valor => $rotulo)
        <option value="{{ $valor }}" @selected(old($campo, $veiculo?->{$campo} ?? '') === $valor)>{{ $rotulo }}</option>
    @endforeach
</select>
@error($campo) <div class="invalid-feedback">{{ $message }}</div> @enderror
