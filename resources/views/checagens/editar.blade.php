@extends('layouts.app')

@section('title', 'Checagem de '.$checagem->tipo->rotulo())
@section('voltar', route('alocacoes.show', $checagem->alocacao))

@section('content')
    <div class="card mb-3">
        <div class="card-body py-2 small d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><strong>{{ $checagem->alocacao->veiculo->nome }}</strong> · {{ $checagem->alocacao->veiculo->placa_formatada }} · {{ $checagem->alocacao->objetivo }}</div>
            <div>
                @if($checagem->anterior)
                    <span class="text-muted">Comparando com a checagem de {{ $checagem->anterior->tipo->rotulo() }} de <strong>{{ $checagem->anterior->motorista->nome }}</strong> em {{ $checagem->anterior->concluida_em->format('d/m H:i') }}</span>
                @else
                    <span class="text-muted">Primeira checagem deste veículo: suas fotos serão a referência.</span>
                @endif
            </div>
        </div>
    </div>

    <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle me-1"></i> Para cada item, veja a foto anterior, tire a sua e responda se está <strong>conforme</strong> ou se há <strong>anomalia</strong> (dano, sujeira, item faltando). Anomalia exige descrição e vira uma ocorrência.
        <span id="chk-progresso" class="fw-semibold" data-pendentes="{{ $checagem->itensPendentes() }}">Faltam {{ $checagem->itensPendentes() }} item(ns).</span>
    </div>

    @foreach(config('frota.checagem.categorias') as $categoria => $def)
        <h6 class="text-muted text-uppercase small mt-3 mb-2">{{ $def['rotulo'] }}</h6>
        <div class="row g-2">
            @foreach($checagem->itens->where('categoria', $categoria) as $item)
                @php($anterior = $anteriores[$item->item] ?? null)
                @php($fotoAnterior = $anterior?->fotoAtual && $anterior->fotoAtual->disponivel() ? route('checagens.foto', $anterior->fotoAtual) : null)
                @php($fotoAtual = $item->fotoAtual && $item->fotoAtual->disponivel() ? route('checagens.foto', $item->fotoAtual) : null)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card h-100 chk-item {{ $item->situacao->value !== 'pendente' && $fotoAtual ? 'border-success' : '' }}"
                         data-url="{{ route('checagens.item', [$checagem, $item]) }}" data-item="{{ $item->item }}">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="small">{{ $item->rotulo }}</strong>
                                <span class="badge badge-situacao {{ $item->situacao->badge() }} chk-badge">{{ $item->situacao->rotulo() }}</span>
                            </div>
                            <div class="row g-1">
                                <div class="col-6 text-center">
                                    <div class="small text-muted">Anterior</div>
                                    @if($fotoAnterior)
                                        <a href="{{ $fotoAnterior }}" target="_blank"><img src="{{ $fotoAnterior }}" class="w-100 rounded" style="height:120px;object-fit:cover" alt="Foto anterior"></a>
                                    @else
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted small" style="height:120px">sem foto</div>
                                    @endif
                                </div>
                                <div class="col-6 text-center">
                                    <div class="small text-muted">Sua foto</div>
                                    <label class="d-block position-relative" style="cursor:pointer">
                                        <img src="{{ $fotoAtual ?? '' }}" class="w-100 rounded chk-preview {{ $fotoAtual ? '' : 'd-none' }}" style="height:120px;object-fit:cover" alt="">
                                        <div class="bg-light rounded d-flex flex-column align-items-center justify-content-center text-muted small chk-placeholder {{ $fotoAtual ? 'd-none' : '' }}" style="height:120px"><i class="bi bi-camera fs-3"></i>tirar foto</div>
                                        <input type="file" accept="image/*" capture="environment" class="d-none chk-arquivo">
                                    </label>
                                </div>
                            </div>
                            <div class="d-flex gap-1 mt-2">
                                <button type="button" class="btn btn-sm flex-fill chk-resposta {{ $item->situacao->value === 'conforme' ? 'btn-success' : 'btn-outline-success' }}" data-valor="conforme"><i class="bi bi-check-lg"></i> Conforme</button>
                                <button type="button" class="btn btn-sm flex-fill chk-resposta {{ $item->situacao->value === 'anomalia' ? 'btn-danger' : 'btn-outline-danger' }}" data-valor="anomalia"><i class="bi bi-exclamation-triangle"></i> Anomalia</button>
                            </div>
                            <input type="text" class="form-control form-control-sm mt-2 chk-observacao {{ $item->situacao->value === 'anomalia' ? '' : 'd-none' }}" maxlength="500" placeholder="Descreva a anomalia (obrigatório)" value="{{ $item->observacao }}">
                            <div class="small text-danger mt-1 chk-erro d-none"></div>
                            <div class="small text-muted mt-1 chk-status d-none"><span class="spinner-border spinner-border-sm"></span> enviando…</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="card mt-4" id="chk-concluir">
        <div class="card-header bg-white"><strong><i class="bi bi-flag"></i> Concluir checagem de {{ $checagem->tipo->rotulo() }}</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('checagens.concluir', $checagem) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label gc-required">Km no odômetro</label>
                        <input type="number" name="km_informado" value="{{ old('km_informado', $kmMinimo) }}" min="{{ $kmMinimo }}" class="form-control @error('km_informado') is-invalid @enderror" required>
                        <div class="form-text">Mínimo: {{ number_format($kmMinimo, 0, ',', '.') }}</div>
                        @error('km_informado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label gc-required">Combustível</label>
                        <select name="nivel_combustivel" class="form-select @error('nivel_combustivel') is-invalid @enderror" required>
                            @foreach($niveis as $v => $r)<option value="{{ $v }}" @selected(old('nivel_combustivel', 'meio') === $v)>{{ $r }}</option>@endforeach
                        </select>
                        @error('nivel_combustivel') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label gc-required">Estado geral do veículo</label>
                        <select name="estado_geral" class="form-select @error('estado_geral') is-invalid @enderror" required>
                            @foreach($estados as $v => $r)<option value="{{ $v }}" @selected(old('estado_geral', $checagem->alocacao->veiculo->estado_atual->value) === $v)>{{ $r }}</option>@endforeach
                        </select>
                        @error('estado_geral') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Observação</label>
                        <input type="text" name="observacao_motorista" value="{{ old('observacao_motorista') }}" maxlength="1000" class="form-control">
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-gc-primary" id="chk-btn-concluir" @disabled($checagem->itensPendentes() > 0)><i class="bi bi-check2-all"></i> Concluir checagem</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>window.CHK_FOTO_MAX_KB = {{ $fotoMaxKb }};</script>
<script src="{{ asset('js/checagem.js') }}?v={{ filemtime(public_path('js/checagem.js')) }}"></script>
@endpush
