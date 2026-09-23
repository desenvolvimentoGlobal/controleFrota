@php($manutencao = $manutencao ?? null)
@php($prefill = $prefill ?? [])
@php($val = fn (string $campo, $padrao = null) => old($campo, $manutencao?->{$campo} ?? ($prefill[$campo] ?? $padrao)))

<div class="row g-3">
    @if($ocorrencia)
        <div class="col-12">
            <div class="alert alert-warning py-2 small mb-0">
                <i class="bi bi-exclamation-diamond me-1"></i> Aberta a partir da <a href="{{ route('ocorrencias.show', $ocorrencia) }}">ocorrência #{{ $ocorrencia->id }}</a>: {{ $ocorrencia->descricao }}
            </div>
            <input type="hidden" name="ocorrencia_id" value="{{ $ocorrencia->id }}">
        </div>
    @endif

    <div class="col-md-5">
        <label class="form-label gc-required">Veículo</label>
        @if($manutencao)
            <input type="text" class="form-control" value="{{ $manutencao->veiculo->nome }} · {{ $manutencao->veiculo->placa_formatada }}" disabled>
        @else
            <select name="veiculo_id" class="form-select @error('veiculo_id') is-invalid @enderror" required @if($ocorrencia) readonly @endif>
                <option value="">Selecione</option>
                @foreach($veiculos as $v)
                    <option value="{{ $v->id }}" @selected((string) $val('veiculo_id') === (string) $v->id)>{{ $v->nome }} · {{ $v->placa_formatada }} · {{ $v->situacao->rotulo() }}</option>
                @endforeach
            </select>
            @error('veiculo_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @endif
    </div>
    <div class="col-md-3">
        <label class="form-label gc-required">Tipo</label>
        <select name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
            @foreach($tipos as $v => $r)
                <option value="{{ $v }}" @selected(($manutencao?->tipo?->value ?? old('tipo', $prefill['tipo'] ?? '')) === $v)>{{ $r }}</option>
            @endforeach
        </select>
        @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label gc-required">Nome do serviço</label>
        <input type="text" name="nome" value="{{ $val('nome') }}" maxlength="150" class="form-control @error('nome') is-invalid @enderror" placeholder="Ex.: Troca de pastilhas de freio" required>
        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label">Descrição do problema</label>
        <textarea name="descricao_problema" rows="3" maxlength="5000" class="form-control @error('descricao_problema') is-invalid @enderror">{{ $val('descricao_problema') }}</textarea>
        @error('descricao_problema') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Fornecedor do serviço</label>
        <select name="fornecedor_id" class="form-select @error('fornecedor_id') is-invalid @enderror">
            <option value="">A definir</option>
            @foreach($fornecedores as $f)
                <option value="{{ $f->id }}" @selected((string) $val('fornecedor_id') === (string) $f->id)>{{ $f->nome }}</option>
            @endforeach
        </select>
        @error('fornecedor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @can('fornecedores.gerenciar')<div class="form-text"><a href="{{ route('fornecedores.create') }}" target="_blank">Cadastrar fornecedor</a></div>@endcan
    </div>
    <div class="col-md-2">
        <label class="form-label">Preço previsto (R$)</label>
        <input type="text" name="preco_previsto" value="{{ old('preco_previsto', \App\Support\Numero::campo($manutencao?->preco_previsto)) }}" class="form-control @error('preco_previsto') is-invalid @enderror" placeholder="0,00">
        @error('preco_previsto') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Prazo</label>
        <input type="date" name="prazo" value="{{ old('prazo', $manutencao?->prazo?->toDateString()) }}" class="form-control @error('prazo') is-invalid @enderror">
        @error('prazo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Localização</label>
        <input type="text" name="localizacao" value="{{ $val('localizacao') }}" maxlength="255" class="form-control @error('localizacao') is-invalid @enderror" placeholder="Onde o veículo ficará">
        @error('localizacao') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Responsável interno</label>
        <select name="responsavel_id" class="form-select @error('responsavel_id') is-invalid @enderror">
            <option value="">—</option>
            @foreach($responsaveis as $r)
                <option value="{{ $r->id }}" @selected((string) $val('responsavel_id', auth()->id()) === (string) $r->id)>{{ $r->nome }}</option>
            @endforeach
        </select>
        @error('responsavel_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-8">
        <label class="form-label">Sistemas mecânicos tratados</label>
        @php($marcados = (array) old('sistemas', $manutencao?->sistemas ?? ($prefill['sistemas'] ?? [])))
        <div class="row g-1">
            @foreach($sistemasMecanicos as $chave => $rotulo)
                <div class="col-6 col-lg-4">
                    <div class="form-check small">
                        <input class="form-check-input" type="checkbox" name="sistemas[]" value="{{ $chave }}" id="sis-{{ $chave }}" @checked(in_array($chave, $marcados, true))>
                        <label class="form-check-label" for="sis-{{ $chave }}">{{ $rotulo }}</label>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="form-text">Ao concluir, os sistemas marcados voltam a <strong>OK</strong> na condição mecânica do veículo.</div>
    </div>

    <div class="col-12">
        <label class="form-label">Observações</label>
        <textarea name="observacoes" rows="2" maxlength="5000" class="form-control">{{ $val('observacoes') }}</textarea>
    </div>

    @unless($manutencao)
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="bloquear_veiculo" id="bloquear_veiculo" value="1" @checked(old('bloquear_veiculo', ($prefill['tipo'] ?? '') === 'imediata'))>
                <label class="form-check-label" for="bloquear_veiculo">Bloquear o veículo agora (indisponível até a conclusão)</label>
            </div>
            <div class="form-text">Use para defeito que impede o uso. Sem bloqueio, o veículo só para quando a prestação começar.</div>
        </div>
    @endunless
</div>
