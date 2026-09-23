@extends('layouts.app')

@section('title', 'Ocorrência #'.$ocorrencia->id)
@section('voltar', route('ocorrencias.index'))

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>{{ $ocorrencia->veiculo->nome }} · {{ $ocorrencia->item->rotulo }}</strong>
                    <span class="badge badge-situacao {{ $ocorrencia->situacao->badge() }}">{{ $ocorrencia->situacao->rotulo() }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6 text-center">
                            <div class="small text-muted">Foto anterior @if($ocorrencia->itemAnterior)· {{ $ocorrencia->itemAnterior->checagem->motorista->nome }} · {{ $ocorrencia->itemAnterior->checagem->concluida_em?->format('d/m H:i') }}@endif</div>
                            @if($ocorrencia->itemAnterior?->fotoAtual?->disponivel())
                                <a href="{{ route('checagens.foto', $ocorrencia->itemAnterior->fotoAtual) }}" target="_blank"><img src="{{ route('checagens.foto', $ocorrencia->itemAnterior->fotoAtual) }}" class="w-100 rounded" style="max-height:320px;object-fit:contain" alt=""></a>
                            @else
                                <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted" style="height:200px">sem foto anterior</div>
                            @endif
                        </div>
                        <div class="col-6 text-center">
                            <div class="small text-muted">Foto da anomalia · {{ $ocorrencia->item->checagem->motorista->nome }} · {{ $ocorrencia->item->checagem->concluida_em?->format('d/m H:i') }}</div>
                            @if($ocorrencia->item->fotoAtual?->disponivel())
                                <a href="{{ route('checagens.foto', $ocorrencia->item->fotoAtual) }}" target="_blank"><img src="{{ route('checagens.foto', $ocorrencia->item->fotoAtual) }}" class="w-100 rounded border border-danger" style="max-height:320px;object-fit:contain" alt=""></a>
                            @else
                                <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted" style="height:200px">foto expirada</div>
                            @endif
                        </div>
                    </div>
                    <p class="mb-1"><strong>Descrição:</strong> {{ $ocorrencia->descricao }}</p>
                    <p class="small text-muted mb-0">Apontada por {{ $ocorrencia->apontadaPor->nome }} em {{ $ocorrencia->created_at->format('d/m/Y H:i') }}, na checagem de {{ $ocorrencia->item->checagem->tipo->rotulo() }} da <a href="{{ route('alocacoes.show', $ocorrencia->item->checagem->alocacao) }}">alocação #{{ $ocorrencia->item->checagem->alocacao_id }}</a>.</p>
                </div>
            </div>

            @if($ocorrencia->contestacao)
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-white"><strong><i class="bi bi-reply"></i> Contestação</strong> <span class="text-muted small">{{ $ocorrencia->responsavel()?->nome }} · {{ $ocorrencia->contestada_em?->format('d/m/Y H:i') }}</span></div>
                    <div class="card-body small" style="white-space:pre-line">{{ $ocorrencia->contestacao }}</div>
                </div>
            @endif

            @if($ocorrencia->revisada_em)
                <div class="card mb-3">
                    <div class="card-header bg-white"><strong><i class="bi bi-person-check"></i> Revisão do gestor</strong> <span class="text-muted small">{{ $ocorrencia->revisadaPor?->nome }} · {{ $ocorrencia->revisada_em->format('d/m/Y H:i') }}</span></div>
                    <div class="card-body small">{{ $ocorrencia->situacao->rotulo() }}. {{ $ocorrencia->observacao_revisao }}</div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Responsável presumido</strong></div>
                <div class="card-body small">
                    @if($ocorrencia->alocacaoResponsavel)
                        <strong>{{ $ocorrencia->alocacaoResponsavel->motorista->nome }}</strong><br>
                        <a href="{{ route('alocacoes.show', $ocorrencia->alocacaoResponsavel) }}">Alocação #{{ $ocorrencia->alocacaoResponsavel->id }}</a> · {{ $ocorrencia->alocacaoResponsavel->objetivo }}<br>
                        <span class="text-muted">{{ $ocorrencia->alocacaoResponsavel->saida_real?->format('d/m H:i') }} → {{ $ocorrencia->alocacaoResponsavel->retorno_real?->format('d/m H:i') }}</span>
                    @else
                        <span class="text-muted">Sem alocação anterior: não há responsável presumido.</span>
                    @endif
                </div>
            </div>

            @can('manutencoes.ver')
                <div class="card mb-3">
                    <div class="card-header bg-white"><strong><i class="bi bi-wrench-adjustable"></i> Manutenção</strong></div>
                    <div class="card-body small">
                        @if($ocorrencia->manutencao)
                            <a href="{{ route('manutencoes.show', $ocorrencia->manutencao) }}">#{{ $ocorrencia->manutencao->id }} {{ $ocorrencia->manutencao->nome }}</a>
                            <span class="badge badge-situacao {{ $ocorrencia->manutencao->situacao->badge() }}">{{ $ocorrencia->manutencao->situacao->rotulo() }}</span>
                        @else
                            <span class="text-muted">Nenhuma manutenção vinculada.</span>
                            @can('manutencoes.gerenciar')
                                <div class="mt-2"><a href="{{ route('manutencoes.create', ['ocorrencia_id' => $ocorrencia->id]) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-wrench-adjustable"></i> Abrir manutenção</a></div>
                            @endcan
                        @endif
                    </div>
                </div>
            @endcan

            @can('contestar', $ocorrencia)
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-white"><strong>Contestar</strong></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('ocorrencias.contestar', $ocorrencia) }}">@csrf @method('PATCH')
                            <textarea name="contestacao" rows="4" maxlength="1000" class="form-control @error('contestacao') is-invalid @enderror" placeholder="Explique por que a anomalia não é de responsabilidade sua (ex.: dano já existia, veja a minha foto)." required></textarea>
                            @error('contestacao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="d-flex justify-content-end mt-2"><button class="btn btn-warning btn-sm"><i class="bi bi-reply"></i> Enviar contestação</button></div>
                        </form>
                    </div>
                </div>
            @endcan

            @can('revisar', $ocorrencia)
                @if($ocorrencia->situacao->value === 'aberta')
                    <div class="card">
                        <div class="card-header bg-white"><strong>Revisar (opcional)</strong></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('ocorrencias.revisar', $ocorrencia) }}">@csrf @method('PATCH')
                                <div class="mb-2">
                                    <label class="form-label gc-required">Decisão</label>
                                    <select name="decisao" class="form-select form-select-sm" required>
                                        <option value="confirmada">Confirmar: o dano é real e a responsabilidade procede</option>
                                        <option value="descartada">Descartar: dano já conhecido, sem responsável ou improcedente</option>
                                    </select>
                                </div>
                                @if($outrasAlocacoes->isNotEmpty())
                                    <div class="mb-2">
                                        <label class="form-label">Reatribuir responsabilidade</label>
                                        <select name="alocacao_responsavel_id" class="form-select form-select-sm">
                                            <option value="">Manter</option>
                                            @foreach($outrasAlocacoes as $a)
                                                <option value="{{ $a->id }}" @selected($a->id === $ocorrencia->alocacao_responsavel_id)>#{{ $a->id }} {{ $a->motorista->nome }} · {{ $a->retorno_real?->format('d/m H:i') }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="mb-2">
                                    <label class="form-label">Observação</label>
                                    <textarea name="observacao_revisao" rows="2" maxlength="1000" class="form-control form-control-sm"></textarea>
                                </div>
                                <div class="d-flex justify-content-end"><button class="btn btn-gc-primary btn-sm" data-gc-confirm="Registrar a revisão desta ocorrência?"><i class="bi bi-check-lg"></i> Registrar revisão</button></div>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan
        </div>
    </div>
@endsection
