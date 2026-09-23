@extends('layouts.app')

@section('title', 'Alocação #'.$alocacao->id)
@section('voltar', route('alocacoes.index'))

@section('content')
    @if($alocacao->atrasada())
        <div class="alert alert-danger py-2 small"><i class="bi bi-alarm me-1"></i> Retorno atrasado: previsto para {{ $alocacao->retorno_previsto->format('d/m/Y H:i') }}.</div>
    @endif
    @if($alocacao->motivo_recusa)
        <div class="alert alert-secondary py-2 small"><i class="bi bi-chat-left-text me-1"></i> Motivo: {{ $alocacao->motivo_recusa }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-0"><a href="{{ route('veiculos.show', $alocacao->veiculo) }}" class="text-decoration-none">{{ $alocacao->veiculo->nome }}</a></h5>
                            <div class="text-muted small">{{ $alocacao->veiculo->descricao }} · {{ $alocacao->veiculo->placa_formatada }}</div>
                        </div>
                        <span class="badge badge-situacao {{ $alocacao->situacao->badge() }}">{{ $alocacao->situacao->rotulo() }}</span>
                    </div>
                    <dl class="row mb-0 small mt-3">
                        <dt class="col-5">Motorista</dt><dd class="col-7">{{ $alocacao->motorista->nome }}</dd>
                        <dt class="col-5">Solicitado por</dt><dd class="col-7">{{ $alocacao->solicitante->nome }} <span class="text-muted">em {{ $alocacao->created_at->format('d/m H:i') }}</span></dd>
                        @if($alocacao->aprovador)
                            <dt class="col-5">{{ $alocacao->situacao->value === 'recusada' ? 'Recusado por' : 'Aprovado por' }}</dt><dd class="col-7">{{ $alocacao->aprovador->nome }} <span class="text-muted">{{ $alocacao->aprovada_em?->format('d/m H:i') }}</span></dd>
                        @endif
                        <dt class="col-5">Objetivo</dt><dd class="col-7">{{ $alocacao->objetivo }}</dd>
                        <dt class="col-5">Destino</dt><dd class="col-7">{{ $alocacao->destino ?? '—' }}</dd>
                        <dt class="col-5">Saída prevista</dt><dd class="col-7">{{ $alocacao->saida_prevista->format('d/m/Y H:i') }}</dd>
                        <dt class="col-5">Retorno previsto</dt><dd class="col-7">{{ $alocacao->retorno_previsto->format('d/m/Y H:i') }}</dd>
                        @if($alocacao->saida_real)
                            <dt class="col-5">Saída real</dt><dd class="col-7">{{ $alocacao->saida_real->format('d/m/Y H:i') }} · {{ number_format($alocacao->km_saida, 0, ',', '.') }} km · {{ $alocacao->estado_saida?->rotulo() }}</dd>
                        @endif
                        @if($alocacao->retorno_real)
                            <dt class="col-5">Retorno real</dt><dd class="col-7">{{ $alocacao->retorno_real->format('d/m/Y H:i') }} · {{ number_format($alocacao->km_retorno, 0, ',', '.') }} km · {{ $alocacao->estado_retorno?->rotulo() }}</dd>
                            <dt class="col-5">Rodados</dt><dd class="col-7">{{ number_format($alocacao->kmRodados(), 0, ',', '.') }} km</dd>
                        @endif
                        @if($alocacao->observacoes)<dt class="col-5">Observações</dt><dd class="col-7">{{ $alocacao->observacoes }}</dd>@endif
                    </dl>

                    <div class="d-flex gap-2 flex-wrap mt-3">
                        @can('checar', $alocacao)
                            <form method="POST" action="{{ route('alocacoes.checagem', $alocacao) }}">@csrf
                                <button class="btn btn-gc-primary"><i class="bi bi-camera"></i> Fazer checagem de {{ $alocacao->proximaChecagem()->rotulo() }}</button>
                            </form>
                        @endcan
                        @can('aprovar', $alocacao)
                            <form method="POST" action="{{ route('alocacoes.aprovar', $alocacao) }}" data-gc-confirm="Aprovar esta alocação?">@csrf @method('PATCH')
                                <button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Aprovar</button>
                            </form>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modal-recusar"><i class="bi bi-x-lg"></i> Recusar</button>
                        @endcan
                        @can('cancelar', $alocacao)
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-cancelar"><i class="bi bi-slash-circle"></i> Cancelar</button>
                        @endcan
                        @can('encerrar', $alocacao)
                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modal-encerrar" title="Quando o motorista não consegue fazer a checagem de retorno"><i class="bi bi-stop-circle"></i> Encerrar sem checagem</button>
                        @endcan
                    </div>
                </div>
            </div>

            @if($alocacao->ocorrenciasComoResponsavel->isNotEmpty())
                <div class="card">
                    <div class="card-header bg-white"><strong><i class="bi bi-exclamation-diamond"></i> Ocorrências apontadas contra esta alocação</strong></div>
                    <ul class="list-group list-group-flush">
                        @foreach($alocacao->ocorrenciasComoResponsavel as $o)
                            <li class="list-group-item small d-flex justify-content-between">
                                <a href="{{ route('ocorrencias.show', $o) }}" class="text-decoration-none">#{{ $o->id }} {{ \Illuminate\Support\Str::limit($o->descricao, 60) }}</a>
                                <span class="badge badge-situacao {{ $o->situacao->badge() }}">{{ $o->situacao->rotulo() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="col-lg-7">
            @forelse($alocacao->checagens as $c)
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong><i class="bi bi-camera"></i> Checagem de {{ $c->tipo->rotulo() }}</strong>
                        @if($c->concluida())
                            <span class="small text-muted">{{ $c->concluida_em->format('d/m/Y H:i') }} · {{ number_format($c->km_informado, 0, ',', '.') }} km · {{ $c->rotuloCombustivel() }} · {{ $c->estado_geral?->rotulo() }}
                                <a href="{{ route('checagens.show', $c) }}" class="btn btn-sm btn-outline-secondary ms-2">Ver comparação</a></span>
                        @else
                            <span class="badge badge-situacao badge-pendente">Rascunho · {{ $c->itensPendentes() }} pendente(s)</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            @foreach($c->itens as $i)
                                <div class="col-4 col-md-3">
                                    <div class="border rounded p-1 text-center small h-100 {{ $i->situacao->value === 'anomalia' ? 'border-danger' : '' }}">
                                        @if($i->fotoAtual && $i->fotoAtual->disponivel())
                                            <a href="{{ route('checagens.foto', $i->fotoAtual) }}" target="_blank"><img src="{{ route('checagens.foto', $i->fotoAtual) }}" class="w-100 rounded" style="height:70px;object-fit:cover" alt=""></a>
                                        @else
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted" style="height:70px"><i class="bi bi-image"></i></div>
                                        @endif
                                        <div class="text-truncate mt-1" title="{{ $i->rotulo }}">{{ $i->rotulo }}</div>
                                        <span class="badge badge-situacao {{ $i->situacao->badge() }}">{{ $i->situacao->rotulo() }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if($c->observacao_motorista)<div class="small text-muted mt-2"><i class="bi bi-chat-left-text"></i> {{ $c->observacao_motorista }}</div>@endif
                    </div>
                </div>
            @empty
                <div class="card"><div class="card-body"><x-empty-state icon="bi-camera" title="Nenhuma checagem ainda" description="A checagem de saída é feita pelo motorista antes de pegar o veículo." /></div></div>
            @endforelse
        </div>
    </div>

    @can('aprovar', $alocacao)
        <div class="modal fade" id="modal-recusar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('alocacoes.recusar', $alocacao) }}">@csrf @method('PATCH')
                <div class="modal-header"><h5 class="modal-title">Recusar alocação</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><label class="form-label gc-required">Motivo</label><textarea name="motivo" rows="3" maxlength="500" class="form-control" required></textarea></div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Voltar</button><button class="btn btn-danger">Recusar</button></div>
            </form>
        </div></div></div>
    @endcan
    @can('encerrar', $alocacao)
        <div class="modal fade" id="modal-encerrar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('alocacoes.encerrar', $alocacao) }}">@csrf @method('PATCH')
                <div class="modal-header"><h5 class="modal-title">Encerrar alocação sem checagem</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="small text-muted">Use só quando o motorista não puder fazer a checagem de retorno (desligamento, celular perdido, acidente). Sem as fotos, avarias deste uso não geram ocorrência automática.</p>
                    <div class="mb-3">
                        <label class="form-label gc-required">Km no odômetro</label>
                        <input type="number" name="km_retorno" min="{{ $alocacao->km_saida ?? 0 }}" value="{{ $alocacao->km_saida }}" class="form-control" required>
                    </div>
                    <label class="form-label gc-required">Motivo</label>
                    <textarea name="motivo" rows="3" maxlength="500" class="form-control" required></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Voltar</button><button class="btn btn-danger">Encerrar</button></div>
            </form>
        </div></div></div>
    @endcan
    @can('cancelar', $alocacao)
        <div class="modal fade" id="modal-cancelar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('alocacoes.cancelar', $alocacao) }}">@csrf @method('PATCH')
                <div class="modal-header"><h5 class="modal-title">Cancelar alocação</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><label class="form-label gc-required">Motivo</label><textarea name="motivo" rows="3" maxlength="500" class="form-control" required></textarea></div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Voltar</button><button class="btn btn-danger">Cancelar alocação</button></div>
            </form>
        </div></div></div>
    @endcan
@endsection
