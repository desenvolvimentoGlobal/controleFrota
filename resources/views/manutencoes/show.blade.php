@extends('layouts.app')

@section('title', 'Manutenção #'.$manutencao->id)
@section('voltar', route('manutencoes.index'))

@php($N = \App\Support\Numero::class)

@section('content')
    @if($manutencao->atrasada())
        <div class="alert alert-danger py-2 small"><i class="bi bi-alarm me-1"></i> Prazo vencido em {{ $manutencao->prazo->format('d/m/Y') }}.</div>
    @endif
    @if($manutencao->motivo_cancelamento)
        <div class="alert alert-secondary py-2 small"><i class="bi bi-slash-circle me-1"></i> Cancelada: {{ $manutencao->motivo_cancelamento }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-0">{{ $manutencao->nome }}</h5>
                            <a href="{{ route('veiculos.show', $manutencao->veiculo) }}" class="small text-decoration-none">{{ $manutencao->veiculo->nome }} · {{ $manutencao->veiculo->placa_formatada }}</a>
                        </div>
                        <span class="badge badge-situacao {{ $manutencao->situacao->badge() }}">{{ $manutencao->situacao->rotulo() }}</span>
                    </div>
                    <div class="mt-2"><span class="badge badge-situacao {{ $manutencao->tipo->badge() }}">{{ $manutencao->tipo->rotulo() }}</span>
                        @if($manutencao->bloqueou_veiculo && $manutencao->situacao->aberta())<span class="badge badge-situacao badge-inativo">veículo bloqueado</span>@endif</div>

                    <dl class="row mb-0 small mt-3">
                        <dt class="col-5">Fornecedor</dt><dd class="col-7">{{ $manutencao->fornecedor?->nome ?? 'A definir' }}@if($manutencao->fornecedor?->telefone)<div class="text-muted">{{ $manutencao->fornecedor->telefone }}</div>@endif</dd>
                        <dt class="col-5">Preço previsto</dt><dd class="col-7 gc-valor-sensivel">{{ $N::moeda($manutencao->preco_previsto) }}</dd>
                        <dt class="col-5">Preço final</dt><dd class="col-7 gc-valor-sensivel fw-semibold">{{ $N::moeda($manutencao->preco_final) }}</dd>
                        <dt class="col-5">Prazo</dt><dd class="col-7">{{ $manutencao->prazo?->format('d/m/Y') ?? '—' }}</dd>
                        <dt class="col-5">Localização</dt><dd class="col-7">{{ $manutencao->localizacao ?? '—' }}</dd>
                        <dt class="col-5">Responsável</dt><dd class="col-7">{{ $manutencao->responsavel?->nome ?? '—' }}</dd>
                        <dt class="col-5">Aberta por</dt><dd class="col-7">{{ $manutencao->abertaPor?->nome ?? 'Sistema (plano preventivo)' }} <span class="text-muted">{{ $manutencao->created_at->format('d/m/Y H:i') }}</span></dd>
                        <dt class="col-5">Km na abertura</dt><dd class="col-7">{{ $N::km($manutencao->km_abertura) }}</dd>
                        @if($manutencao->inicio_prestacao_em)<dt class="col-5">Início da prestação</dt><dd class="col-7">{{ $manutencao->inicio_prestacao_em->format('d/m/Y H:i') }}</dd>@endif
                        @if($manutencao->concluida_em)<dt class="col-5">Concluída</dt><dd class="col-7">{{ $manutencao->concluida_em->format('d/m/Y H:i') }} · {{ $N::km($manutencao->km_conclusao) }}</dd>@endif
                        @if($manutencao->sistemas)<dt class="col-5">Sistemas</dt><dd class="col-7">{{ implode(', ', $manutencao->rotulosSistemas()) }}</dd>@endif
                        @if($manutencao->ocorrencia)<dt class="col-5">Origem</dt><dd class="col-7"><a href="{{ route('ocorrencias.show', $manutencao->ocorrencia) }}">Ocorrência #{{ $manutencao->ocorrencia->id }}</a> · {{ $manutencao->ocorrencia->item->rotulo }}</dd>@endif
                        @if($manutencao->plano)<dt class="col-5">Origem</dt><dd class="col-7">Plano "{{ $manutencao->plano->nome }}" ({{ $manutencao->plano->descricaoIntervalo() }})</dd>@endif
                    </dl>
                    @if($manutencao->descricao_problema)
                        <div class="small mt-3"><strong>Problema</strong><div style="white-space:pre-line">{{ $manutencao->descricao_problema }}</div></div>
                    @endif
                    @if($manutencao->observacoes)
                        <div class="small mt-2 text-muted" style="white-space:pre-line">{{ $manutencao->observacoes }}</div>
                    @endif

                    @can('manutencoes.gerenciar')
                        @if($manutencao->situacao->aberta())
                            <div class="d-flex gap-2 flex-wrap mt-3">
                                @if($manutencao->situacao->value === 'em_espera')
                                    <form method="POST" action="{{ route('manutencoes.iniciar', $manutencao) }}" data-gc-confirm="Iniciar a prestação? O veículo ficará EM MANUTENÇÃO e não poderá ser alocado.">@csrf @method('PATCH')
                                        <button class="btn btn-gc-primary btn-sm"><i class="bi bi-play-fill"></i> Iniciar prestação</button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modal-concluir"><i class="bi bi-check2-all"></i> Concluir</button>
                                @endif
                                <a href="{{ route('manutencoes.edit', $manutencao) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Editar</a>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modal-cancelar"><i class="bi bi-slash-circle"></i> Cancelar</button>
                            </div>
                        @endif
                    @endcan
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white"><strong><i class="bi bi-paperclip"></i> Anexos</strong> <span class="text-muted small">orçamentos, notas fiscais, fotos</span></div>
                <ul class="list-group list-group-flush">
                    @forelse($manutencao->anexos as $a)
                        <li class="list-group-item small d-flex justify-content-between align-items-center gap-2">
                            <span>
                                <i class="bi {{ $a->ehImagem() ? 'bi-file-image' : 'bi-file-earmark' }}"></i>
                                <a href="{{ route('manutencoes.anexo', $a) }}" target="_blank">{{ $a->titulo ?: $a->nome_original }}</a>
                                <span class="text-muted">· {{ $a->tamanhoLegivel() }} · {{ $a->enviadoPor?->nome }} · {{ $a->created_at->format('d/m H:i') }}</span>
                            </span>
                            @can('manutencoes.gerenciar')
                                <button type="button" class="btn btn-sm btn-outline-danger" data-confirm-delete data-url="{{ route('manutencoes.anexo.remover', $a) }}"
                                    data-title="Remover anexo" data-message="Remover &quot;{{ $a->titulo ?: $a->nome_original }}&quot;?"><i class="bi bi-trash"></i></button>
                            @endcan
                        </li>
                    @empty
                        <li class="list-group-item small text-muted">Nenhum anexo.</li>
                    @endforelse
                </ul>
                @can('manutencoes.anotar')
                    <div class="card-footer bg-white">
                        <form method="POST" action="{{ route('manutencoes.anexar', $manutencao) }}" enctype="multipart/form-data" class="row g-2">
                            @csrf
                            <div class="col-md-5"><input type="text" name="titulo" maxlength="120" class="form-control form-control-sm" placeholder="Título (ex.: Nota fiscal)"></div>
                            <div class="col-md-5"><input type="file" name="arquivo" class="form-control form-control-sm @error('arquivo') is-invalid @enderror" required>
                                @error('arquivo') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-upload"></i></button></div>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white"><strong><i class="bi bi-clock-history"></i> Histórico</strong></div>
                @can('manutencoes.anotar')
                    <div class="card-body border-bottom">
                        <form method="POST" action="{{ route('manutencoes.comentar', $manutencao) }}" class="d-flex gap-2">
                            @csrf
                            <input type="text" name="comentario" maxlength="1000" class="form-control form-control-sm @error('comentario') is-invalid @enderror" placeholder="Registrar andamento, contato com a oficina, orçamento recebido..." required>
                            <button class="btn btn-sm btn-gc-primary text-nowrap"><i class="bi bi-chat-left-text"></i> Registrar</button>
                        </form>
                    </div>
                @endcan
                <ul class="list-group list-group-flush">
                    @foreach($manutencao->movimentacoes as $mov)
                        <li class="list-group-item small d-flex gap-3">
                            <i class="bi {{ $mov->icone() }} text-muted mt-1"></i>
                            <div class="flex-grow-1">
                                <div>{{ $mov->descricao }}</div>
                                @if($mov->valores_novos && $mov->tipo === 'alteracao')
                                    <div class="text-muted">
                                        @foreach($mov->valores_novos as $campo => $novo)
                                            <div>{{ $campo }}: {{ is_array($mov->valores_antigos[$campo] ?? null) ? '' : ($mov->valores_antigos[$campo] ?? '—') }} <i class="bi bi-arrow-right"></i> {{ is_array($novo) ? '' : ($novo ?? '—') }}</div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="text-muted text-nowrap text-end">{{ $mov->usuario?->nome ?? 'sistema' }}<br>{{ $mov->created_at?->format('d/m/Y H:i') }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    @can('manutencoes.gerenciar')
        <div class="modal fade" id="modal-concluir" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('manutencoes.concluir', $manutencao) }}">@csrf @method('PATCH')
                <div class="modal-header"><h5 class="modal-title">Concluir manutenção</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label gc-required">Preço final (R$)</label>
                            <input type="text" name="preco_final" value="{{ \App\Support\Numero::campo($manutencao->preco_previsto) }}" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Km na devolução</label>
                            <input type="number" name="km_conclusao" value="{{ $manutencao->veiculo->km_atual }}" min="{{ $manutencao->veiculo->km_atual }}" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Estado físico do veículo</label>
                            <select name="estado_atual" class="form-select">
                                <option value="">Manter ({{ $manutencao->veiculo->estado_atual->rotulo() }})</option>
                                @foreach($estados as $v => $r)<option value="{{ $v }}">{{ $r }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observação</label>
                            <input type="text" name="observacao" maxlength="500" class="form-control">
                        </div>
                    </div>
                    @if($manutencao->sistemas)<p class="small text-muted mt-3 mb-0">Sistemas que voltarão a OK: {{ implode(', ', $manutencao->rotulosSistemas()) }}.</p>@endif
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Voltar</button><button class="btn btn-success">Concluir</button></div>
            </form>
        </div></div></div>

        <div class="modal fade" id="modal-cancelar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('manutencoes.cancelar', $manutencao) }}">@csrf @method('PATCH')
                <div class="modal-header"><h5 class="modal-title">Cancelar manutenção</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><label class="form-label gc-required">Motivo</label><textarea name="motivo" rows="3" maxlength="500" class="form-control" required></textarea></div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Voltar</button><button class="btn btn-danger">Cancelar manutenção</button></div>
            </form>
        </div></div></div>
    @endcan
@endsection
