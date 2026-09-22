@extends('layouts.app')

@section('title', $veiculo->nome)
@section('voltar', route('veiculos.index'))

@php
    use App\Enums\CaracteristicasVeiculo as C;
    $rot = fn (array $lista, ?string $v) => $v === null ? '—' : ($lista[$v] ?? $v);
    $sim = fn (bool $v) => $v ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>';
@endphp

@section('content')

    @foreach($veiculo->avisos() as $aviso)
        <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i> {{ $aviso }}</div>
    @endforeach
    @if($veiculo->temCondicaoCritica())
        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-octagon me-1"></i> Há sistema mecânico em estado crítico. O veículo não pode ser alocado até a correção.</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    @if($veiculo->foto_url)
                        <img src="{{ $veiculo->foto_url }}" alt="{{ $veiculo->nome }}" class="rounded mb-3 w-100" style="max-height:220px;object-fit:cover">
                    @else
                        <div class="rounded bg-soft-navy d-flex align-items-center justify-content-center mb-3" style="height:160px"><i class="bi bi-car-front fs-1"></i></div>
                    @endif
                    <h5 class="mb-0">{{ $veiculo->nome }}</h5>
                    <div class="text-muted small">{{ $veiculo->descricao }}</div>
                    <div class="mt-2"><code class="fs-6">{{ $veiculo->placa_formatada }}</code></div>
                    <div class="mt-2 d-flex gap-1 flex-wrap">
                        <span class="badge badge-situacao {{ $veiculo->situacao->badge() }}">{{ $veiculo->situacao->rotulo() }}</span>
                        <span class="badge badge-situacao {{ $veiculo->estado_atual->badge() }}">Estado: {{ $veiculo->estado_atual->rotulo() }}</span>
                    </div>
                    <div class="mt-3 small">
                        <div><strong>{{ number_format($veiculo->km_atual, 0, ',', '.') }} km</strong> atuais <span class="text-muted">(inicial {{ number_format($veiculo->km_inicial, 0, ',', '.') }})</span></div>
                        <div class="text-muted">Estado inicial: {{ $veiculo->estado_inicial->rotulo() }}</div>
                    </div>
                    @can('frota.gerenciar')
                        <div class="d-flex gap-2 mt-3 flex-wrap">
                            <a href="{{ route('veiculos.edit', $veiculo) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Editar</a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-situacao"><i class="bi bi-toggle-on"></i> Situação</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-estado"><i class="bi bi-speedometer2"></i> Estado / km</button>
                        </div>
                    @endcan
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-dados" type="button">Dados</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-condicao" type="button">Condição mecânica @if($veiculo->temCondicaoCritica())<span class="badge text-bg-danger ms-1">!</span>@endif</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-historico" type="button">Histórico</button></li>
                    </ul>
                </div>
                <div class="card-body tab-content">

                    {{-- ===== Dados ===== --}}
                    <div class="tab-pane fade show active" id="tab-dados">
                        <div class="row small">
                            <div class="col-md-6">
                                <h6 class="text-muted text-uppercase small">Identificação</h6>
                                <dl class="row mb-3">
                                    <dt class="col-5">Chassi</dt><dd class="col-7">{{ $veiculo->chassi ?? '—' }}</dd>
                                    <dt class="col-5">RENAVAM</dt><dd class="col-7">{{ $veiculo->renavam ?? '—' }}</dd>
                                    <dt class="col-5">Carroceria</dt><dd class="col-7">{{ $rot(C::CARROCERIAS, $veiculo->carroceria) }}</dd>
                                    <dt class="col-5">Cor</dt><dd class="col-7">{{ $veiculo->cor ?? '—' }} {{ $veiculo->tipo_cor ? '('.$rot(C::TIPOS_COR, $veiculo->tipo_cor).')' : '' }}</dd>
                                    <dt class="col-5">Portas / lugares</dt><dd class="col-7">{{ $veiculo->portas ?? '—' }} / {{ $veiculo->lugares ?? '—' }}</dd>
                                </dl>
                                <h6 class="text-muted text-uppercase small">Mecânica</h6>
                                <dl class="row mb-3">
                                    <dt class="col-5">Motor</dt><dd class="col-7">{{ $veiculo->motor ?? '—' }} {{ $veiculo->potencia_cv ? "· {$veiculo->potencia_cv} cv" : '' }}</dd>
                                    <dt class="col-5">Combustível</dt><dd class="col-7">{{ $rot(C::COMBUSTIVEIS, $veiculo->combustivel) }}</dd>
                                    <dt class="col-5">Câmbio</dt><dd class="col-7">{{ $rot(C::CAMBIOS, $veiculo->cambio) }}</dd>
                                    <dt class="col-5">Tração</dt><dd class="col-7">{{ $rot(C::TRACOES, $veiculo->tracao) }}</dd>
                                    <dt class="col-5">Direção</dt><dd class="col-7">{{ $rot(C::DIRECOES, $veiculo->direcao) }}</dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted text-uppercase small">Conforto</h6>
                                <dl class="row mb-3">
                                    <dt class="col-6">Ar-condicionado</dt><dd class="col-6">{{ $rot(C::AR_CONDICIONADO, $veiculo->ar_condicionado) }}</dd>
                                    <dt class="col-6">Bancos</dt><dd class="col-6">{{ $rot(C::BANCOS, $veiculo->bancos) }}</dd>
                                    @foreach(C::CONFORTO as $campo => $rotulo)
                                        <dt class="col-6 fw-normal">{{ $rotulo }}</dt><dd class="col-6">{!! $sim($veiculo->{$campo}) !!}</dd>
                                    @endforeach
                                </dl>
                                <h6 class="text-muted text-uppercase small">Segurança</h6>
                                <dl class="row mb-3">
                                    @foreach(C::SEGURANCA as $campo => $rotulo)
                                        <dt class="col-6 fw-normal">{{ $rotulo }}</dt><dd class="col-6">{!! $sim($veiculo->{$campo}) !!}</dd>
                                    @endforeach
                                    <dt class="col-6">Airbags</dt><dd class="col-6">{{ $veiculo->airbags ?? '—' }}</dd>
                                    <dt class="col-6">Latin NCAP</dt><dd class="col-6">{{ $veiculo->nota_latin_ncap !== null ? str_repeat('★', $veiculo->nota_latin_ncap).str_repeat('☆', 5 - $veiculo->nota_latin_ncap) : '—' }}</dd>
                                </dl>
                            </div>
                            <div class="col-12">
                                <h6 class="text-muted text-uppercase small">Controle</h6>
                                <dl class="row mb-0">
                                    <dt class="col-md-3">Aquisição</dt><dd class="col-md-9">{{ $veiculo->data_aquisicao?->format('d/m/Y') ?? '—' }} {{ $veiculo->valor_aquisicao !== null ? '· R$ '.number_format((float) $veiculo->valor_aquisicao, 2, ',', '.') : '' }}</dd>
                                    <dt class="col-md-3">Licenciamento</dt><dd class="col-md-9">{{ $veiculo->licenciamento_validade?->format('d/m/Y') ?? '—' }}</dd>
                                    <dt class="col-md-3">Seguro</dt><dd class="col-md-9">{{ $veiculo->seguro_validade?->format('d/m/Y') ?? '—' }} {{ $veiculo->seguradora ? "· {$veiculo->seguradora}" : '' }}</dd>
                                    @if($veiculo->observacoes)
                                        <dt class="col-md-3">Observações</dt><dd class="col-md-9" style="white-space:pre-line">{{ $veiculo->observacoes }}</dd>
                                    @endif
                                </dl>
                            </div>
                        </div>
                    </div>

                    {{-- ===== Condição mecânica ===== --}}
                    <div class="tab-pane fade" id="tab-condicao">
                        <form method="POST" action="{{ route('veiculos.condicoes', $veiculo) }}">
                            @csrf @method('PUT')
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-3">
                                    <thead><tr><th>Sistema</th><th style="width:170px">Situação</th><th>Observação</th><th class="small text-muted" style="width:170px">Atualizado</th></tr></thead>
                                    <tbody>
                                        @foreach($veiculo->condicoes as $c)
                                            <tr>
                                                <td class="small fw-semibold">{{ $c->rotulo_sistema }}</td>
                                                <td>
                                                    @can('frota.gerenciar')
                                                        <select name="condicoes[{{ $c->sistema }}][situacao]" class="form-select form-select-sm">
                                                            @foreach(\App\Enums\SituacaoCondicao::cases() as $s)
                                                                <option value="{{ $s->value }}" @selected($c->situacao === $s)>{{ $s->rotulo() }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <span class="badge badge-situacao {{ $c->situacao->badge() }}">{{ $c->situacao->rotulo() }}</span>
                                                    @endcan
                                                </td>
                                                <td>
                                                    @can('frota.gerenciar')
                                                        <input type="text" name="condicoes[{{ $c->sistema }}][observacao]" value="{{ $c->observacao }}" maxlength="500" class="form-control form-control-sm">
                                                    @else
                                                        <span class="small">{{ $c->observacao ?? '—' }}</span>
                                                    @endcan
                                                </td>
                                                <td class="small text-muted">{{ $c->atualizadoPor?->nome ?? '—' }}<br>{{ $c->updated_at?->format('d/m/Y H:i') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @can('frota.gerenciar')
                                <div class="d-flex justify-content-end"><button class="btn btn-gc-primary btn-sm"><i class="bi bi-check-lg"></i> Salvar condições</button></div>
                            @endcan
                        </form>
                    </div>

                    {{-- ===== Histórico ===== --}}
                    <div class="tab-pane fade" id="tab-historico">
                        @forelse($veiculo->historicoEstados as $h)
                            <div class="d-flex gap-3 py-2 border-bottom small">
                                <div class="text-muted text-nowrap" style="width:120px">{{ $h->created_at?->format('d/m/Y H:i') }}</div>
                                <div class="flex-grow-1">
                                    <strong>{{ $h->rotuloCampo() }}</strong>: {{ $h->rotuloValor($h->valor_anterior) }} <i class="bi bi-arrow-right"></i> {{ $h->rotuloValor($h->valor_novo) }}
                                    <span class="badge text-bg-light border ms-1">{{ $h->origem }}</span>
                                    @if($h->observacao)<div class="text-muted">{{ $h->observacao }}</div>@endif
                                </div>
                                <div class="text-muted text-nowrap">{{ $h->usuario?->nome ?? 'sistema' }}</div>
                            </div>
                        @empty
                            <x-empty-state icon="bi-clock-history" title="Sem histórico" />
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('frota.gerenciar')
        {{-- Modal: situação manual --}}
        <div class="modal fade" id="modal-situacao" tabindex="-1">
            <div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('veiculos.situacao', $veiculo) }}">
                    @csrf @method('PATCH')
                    <div class="modal-header"><h5 class="modal-title">Alterar situação</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="small text-muted">Reservado, em uso e em manutenção são definidos pelo fluxo de alocação e manutenção. Aqui só as situações manuais.</p>
                        <div class="mb-3">
                            <label class="form-label gc-required">Nova situação</label>
                            <select name="situacao" class="form-select">
                                @foreach($situacoesManuais as $s)
                                    <option value="{{ $s->value }}" @selected($veiculo->situacao === $s)>{{ $s->rotulo() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label gc-required">Motivo</label>
                            <input type="text" name="observacao" maxlength="255" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-gc-primary">Salvar</button></div>
                </form>
            </div></div>
        </div>

        {{-- Modal: estado físico e km --}}
        <div class="modal fade" id="modal-estado" tabindex="-1">
            <div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('veiculos.estado', $veiculo) }}">
                    @csrf @method('PATCH')
                    <div class="modal-header"><h5 class="modal-title">Ajustar estado físico e km</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="small text-muted">Normalmente isto muda pelas checagens. Use para correções de cadastro. A quilometragem nunca diminui.</p>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label gc-required">Estado físico</label>
                                <select name="estado_atual" class="form-select">
                                    @foreach($estados as $e)
                                        <option value="{{ $e->value }}" @selected($veiculo->estado_atual === $e)>{{ $e->rotulo() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label gc-required">Km atual</label>
                                <input type="number" name="km_atual" value="{{ $veiculo->km_atual }}" min="{{ $veiculo->km_atual }}" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label gc-required">Motivo</label>
                                <input type="text" name="observacao" maxlength="255" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-gc-primary">Salvar</button></div>
                </form>
            </div></div>
        </div>
    @endcan
@endsection
