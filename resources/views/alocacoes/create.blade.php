@extends('layouts.app')

@section('title', 'Nova alocação')
@section('voltar', route('alocacoes.index'))

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <form method="POST" action="{{ route('alocacoes.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label gc-required">Veículo</label>
                            <select name="veiculo_id" class="form-select @error('veiculo_id') is-invalid @enderror" required>
                                <option value="">Selecione</option>
                                @foreach($veiculos as $v)
                                    @php($bloqueios = $v->impedimentosParaAlocar())
                                    <option value="{{ $v->id }}" @selected(old('veiculo_id', $veiculoId) == $v->id) @disabled($bloqueios !== [] && $v->situacao->value !== 'reservado' && $v->situacao->value !== 'em_uso')>
                                        {{ $v->nome }} · {{ $v->placa_formatada }} · {{ $v->situacao->rotulo() }}{{ $v->temCondicaoCritica() ? ' · CRÍTICO' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Veículos indisponíveis, em manutenção, baixados ou com sistema crítico não aparecem habilitados. Reservado/em uso pode ser agendado para outra data.</div>
                            @error('veiculo_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label gc-required">Motorista</label>
                            <select name="motorista_id" class="form-select @error('motorista_id') is-invalid @enderror" required>
                                @foreach($motoristas as $m)
                                    <option value="{{ $m->id }}" @selected(old('motorista_id', auth()->id()) == $m->id)>{{ $m->nome }}{{ $m->avisoHabilitacao() ? ' (⚠ '.$m->avisoHabilitacao().')' : '' }}</option>
                                @endforeach
                            </select>
                            @error('motorista_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label gc-required">Objetivo</label>
                            <input type="text" name="objetivo" value="{{ old('objetivo') }}" maxlength="255" class="form-control @error('objetivo') is-invalid @enderror" placeholder="Ex.: Instalação no cliente X" required>
                            @error('objetivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Destino</label>
                            <input type="text" name="destino" value="{{ old('destino') }}" maxlength="255" class="form-control @error('destino') is-invalid @enderror">
                            @error('destino') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label gc-required">Saída prevista</label>
                            <input type="datetime-local" name="saida_prevista" value="{{ old('saida_prevista', now()->addHour()->format('Y-m-d\TH:00')) }}" class="form-control @error('saida_prevista') is-invalid @enderror" required>
                            @error('saida_prevista') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label gc-required">Retorno previsto</label>
                            <input type="datetime-local" name="retorno_previsto" value="{{ old('retorno_previsto', now()->addHours(5)->format('Y-m-d\TH:00')) }}" class="form-control @error('retorno_previsto') is-invalid @enderror" required>
                            @error('retorno_previsto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" rows="2" maxlength="2000" class="form-control">{{ old('observacoes') }}</textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('alocacoes.index') }}" class="btn btn-light">Cancelar</a>
                        <button class="btn btn-gc-primary"><i class="bi bi-check-lg"></i> {{ auth()->user()->can('alocacoes.aprovar') ? 'Alocar' : 'Solicitar' }}</button>
                    </div>
                </form>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card"><div class="card-body small text-muted">
                <h6 class="text-uppercase small">Como funciona</h6>
                <ol class="ps-3 mb-0">
                    <li>{{ auth()->user()->can('alocacoes.aprovar') ? 'Sua alocação já nasce aprovada.' : 'Seu gestor aprova a solicitação.' }}</li>
                    <li>Antes de sair, o motorista faz a <strong>checagem de saída</strong> pelo celular, comparando cada foto com a última do veículo.</li>
                    <li>Na volta, faz a <strong>checagem de retorno</strong>. Anomalias viram ocorrências.</li>
                </ol>
            </div></div>
        </div>
    </div>
@endsection
