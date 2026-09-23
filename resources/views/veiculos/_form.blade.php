@php($veiculo = $veiculo ?? null)
@php($sel = fn (string $campo, array $opcoes, ?string $vazio = '—') => view('veiculos._select', ['campo' => $campo, 'opcoes' => $opcoes, 'vazio' => $vazio, 'veiculo' => $veiculo]))

<div class="row g-3">

    <div class="col-12"><h6 class="text-muted text-uppercase small mb-0">Identificação</h6></div>

    <div class="col-md-4">
        <label class="form-label gc-required">Nome do veículo</label>
        <input type="text" name="nome" value="{{ old('nome', $veiculo->nome ?? '') }}" maxlength="80" class="form-control @error('nome') is-invalid @enderror" placeholder="Ex.: Onix branco 02">
        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2">
        <label class="form-label gc-required">Placa</label>
        <input type="text" name="placa" value="{{ old('placa', $veiculo->placa ?? '') }}" maxlength="8" class="form-control text-uppercase @error('placa') is-invalid @enderror" placeholder="ABC1D23">
        @error('placa') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Chassi (VIN)</label>
        <input type="text" name="chassi" value="{{ old('chassi', $veiculo->chassi ?? '') }}" maxlength="17" class="form-control text-uppercase @error('chassi') is-invalid @enderror">
        @error('chassi') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">RENAVAM</label>
        <input type="text" name="renavam" value="{{ old('renavam', $veiculo->renavam ?? '') }}" maxlength="11" class="form-control @error('renavam') is-invalid @enderror">
        @error('renavam') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 mt-4"><h6 class="text-muted text-uppercase small mb-0">Descrição</h6></div>

    <div class="col-md-3">
        <label class="form-label gc-required">Marca</label>
        <input type="text" name="marca" value="{{ old('marca', $veiculo->marca ?? '') }}" maxlength="60" class="form-control @error('marca') is-invalid @enderror" placeholder="Chevrolet">
        @error('marca') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label gc-required">Modelo</label>
        <input type="text" name="modelo" value="{{ old('modelo', $veiculo->modelo ?? '') }}" maxlength="60" class="form-control @error('modelo') is-invalid @enderror" placeholder="Onix">
        @error('modelo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Versão</label>
        <input type="text" name="versao" value="{{ old('versao', $veiculo->versao ?? '') }}" maxlength="60" class="form-control @error('versao') is-invalid @enderror" placeholder="LT">
        @error('versao') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2">
        <label class="form-label gc-required">Ano fabricação</label>
        <input type="number" name="ano_fabricacao" value="{{ old('ano_fabricacao', $veiculo->ano_fabricacao ?? '') }}" min="1950" max="{{ date('Y') + 1 }}" class="form-control @error('ano_fabricacao') is-invalid @enderror">
        @error('ano_fabricacao') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2">
        <label class="form-label gc-required">Ano modelo</label>
        <input type="number" name="ano_modelo" value="{{ old('ano_modelo', $veiculo->ano_modelo ?? '') }}" min="1950" max="{{ date('Y') + 1 }}" class="form-control @error('ano_modelo') is-invalid @enderror">
        @error('ano_modelo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3"><label class="form-label">Carroceria</label>{{ $sel('carroceria', $carrocerias) }}</div>
    <div class="col-md-3">
        <label class="form-label">Cor</label>
        <input type="text" name="cor" value="{{ old('cor', $veiculo->cor ?? '') }}" maxlength="40" class="form-control @error('cor') is-invalid @enderror">
        @error('cor') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2"><label class="form-label">Tipo de cor</label>{{ $sel('tipo_cor', $tiposCor) }}</div>
    <div class="col-md-2">
        <label class="form-label">Portas</label>
        <input type="number" name="portas" value="{{ old('portas', $veiculo->portas ?? '') }}" min="2" max="6" class="form-control @error('portas') is-invalid @enderror">
        @error('portas') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Lugares</label>
        <input type="number" name="lugares" value="{{ old('lugares', $veiculo->lugares ?? '') }}" min="1" max="20" class="form-control @error('lugares') is-invalid @enderror">
        @error('lugares') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 mt-4"><h6 class="text-muted text-uppercase small mb-0">Mecânica</h6></div>

    <div class="col-md-2">
        <label class="form-label">Motor</label>
        <input type="text" name="motor" value="{{ old('motor', $veiculo->motor ?? '') }}" maxlength="20" class="form-control @error('motor') is-invalid @enderror" placeholder="1.0 Turbo">
        @error('motor') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Potência (cv)</label>
        <input type="number" name="potencia_cv" value="{{ old('potencia_cv', $veiculo->potencia_cv ?? '') }}" min="1" max="2000" class="form-control @error('potencia_cv') is-invalid @enderror">
        @error('potencia_cv') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2"><label class="form-label">Combustível</label>{{ $sel('combustivel', $combustiveis) }}</div>
    <div class="col-md-2"><label class="form-label">Câmbio</label>{{ $sel('cambio', $cambios) }}</div>
    <div class="col-md-2"><label class="form-label">Tração</label>{{ $sel('tracao', $tracoes) }}</div>
    <div class="col-md-2"><label class="form-label">Direção</label>{{ $sel('direcao', $direcoes) }}</div>

    <div class="col-12 mt-4"><h6 class="text-muted text-uppercase small mb-0">Conforto e conectividade</h6></div>

    <div class="col-md-3"><label class="form-label">Ar-condicionado</label>{{ $sel('ar_condicionado', $arCondicionado) }}</div>
    <div class="col-md-3"><label class="form-label">Bancos</label>{{ $sel('bancos', $bancos) }}</div>
    <div class="col-md-6">
        <div class="row g-2 pt-md-4">
            @foreach($conforto as $campo => $rotulo)
                <div class="col-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="{{ $campo }}" id="{{ $campo }}" value="1" @checked(session()->hasOldInput() ? old($campo) : ($veiculo?->{$campo} ?? false))>
                        <label class="form-check-label small" for="{{ $campo }}">{{ $rotulo }}</label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="col-12 mt-4"><h6 class="text-muted text-uppercase small mb-0">Segurança</h6></div>

    <div class="col-md-6">
        <div class="row g-2">
            @foreach($seguranca as $campo => $rotulo)
                <div class="col-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="{{ $campo }}" id="{{ $campo }}" value="1" @checked(session()->hasOldInput() ? old($campo) : ($veiculo?->{$campo} ?? false))>
                        <label class="form-check-label small" for="{{ $campo }}">{{ $rotulo }}</label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Airbags</label>
        <input type="text" name="airbags" value="{{ old('airbags', $veiculo->airbags ?? '') }}" maxlength="60" class="form-control @error('airbags') is-invalid @enderror" placeholder="frontais e laterais">
        @error('airbags') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Latin NCAP (0–5)</label>
        <input type="number" name="nota_latin_ncap" value="{{ old('nota_latin_ncap', $veiculo->nota_latin_ncap ?? '') }}" min="0" max="5" class="form-control @error('nota_latin_ncap') is-invalid @enderror">
        @error('nota_latin_ncap') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 mt-4"><h6 class="text-muted text-uppercase small mb-0">Controle</h6></div>

    @unless($veiculo)
        <div class="col-md-3">
            <label class="form-label gc-required">Estado inicial</label>
            <select name="estado_inicial" class="form-select @error('estado_inicial') is-invalid @enderror">
                @foreach($condicoes as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(old('estado_inicial', 'bom') === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
            <div class="form-text">Condição física no momento do cadastro. Não muda depois.</div>
            @error('estado_inicial') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label gc-required">Km inicial</label>
            <input type="number" name="km_inicial" value="{{ old('km_inicial', 0) }}" min="0" class="form-control @error('km_inicial') is-invalid @enderror">
            @error('km_inicial') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endunless

    <div class="col-md-3">
        <label class="form-label">Data de aquisição</label>
        <input type="date" name="data_aquisicao" value="{{ old('data_aquisicao', $veiculo?->data_aquisicao?->toDateString()) }}" class="form-control @error('data_aquisicao') is-invalid @enderror">
        @error('data_aquisicao') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Valor de aquisição (R$)</label>
        <input type="text" name="valor_aquisicao" value="{{ old('valor_aquisicao', $veiculo?->valor_aquisicao !== null ? number_format((float) $veiculo->valor_aquisicao, 2, ',', '.') : '') }}" class="form-control @error('valor_aquisicao') is-invalid @enderror" placeholder="0,00">
        @error('valor_aquisicao') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Licenciamento válido até</label>
        <input type="date" name="licenciamento_validade" value="{{ old('licenciamento_validade', $veiculo?->licenciamento_validade?->toDateString()) }}" class="form-control @error('licenciamento_validade') is-invalid @enderror">
        @error('licenciamento_validade') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Seguro válido até</label>
        <input type="date" name="seguro_validade" value="{{ old('seguro_validade', $veiculo?->seguro_validade?->toDateString()) }}" class="form-control @error('seguro_validade') is-invalid @enderror">
        @error('seguro_validade') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Seguradora</label>
        <input type="text" name="seguradora" value="{{ old('seguradora', $veiculo->seguradora ?? '') }}" maxlength="100" class="form-control @error('seguradora') is-invalid @enderror">
        @error('seguradora') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Foto</label>
        <input type="file" name="foto" accept="image/*" class="form-control @error('foto') is-invalid @enderror">
        @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @if($veiculo?->foto_path)
            <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" name="remover_foto" id="remover_foto" value="1">
                <label class="form-check-label small" for="remover_foto">Remover a foto atual</label>
            </div>
        @endif
    </div>
    <div class="col-12">
        <label class="form-label">Observações</label>
        <textarea name="observacoes" rows="3" maxlength="2000" class="form-control @error('observacoes') is-invalid @enderror">{{ old('observacoes', $veiculo->observacoes ?? '') }}</textarea>
        @error('observacoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
