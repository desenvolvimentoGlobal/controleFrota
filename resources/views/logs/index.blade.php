@extends('layouts.app')

@section('title', 'Logs do sistema')

@section('content')

    <h1 class="h4 mb-3">Logs do sistema</h1>

    <div class="card card-body mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">De</label>
                <input type="date" name="de" value="{{ $de }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Até</label>
                <input type="date" name="ate" value="{{ $ate }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Usuário</label>
                <select name="usuario_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($usuarios as $usuario)
                        <option value="{{ $usuario->id }}" @selected(request('usuario_id') == $usuario->id)>{{ $usuario->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Ação</label>
                <select name="acao" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($acoes as $acao)
                        <option value="{{ $acao }}" @selected(request('acao') === $acao)>{{ \App\Models\LogAuditoria::rotulo($acao) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Módulo</label>
                <select name="modulo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($modulos as $modulo)
                        <option value="{{ $modulo }}" @selected(request('modulo') === $modulo)>{{ \App\Models\LogAuditoria::rotulo($modulo) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Buscar</label>
                <input type="text" name="busca" value="{{ $busca }}" class="form-control form-control-sm" placeholder="Descrição, tabela ou IP">
            </div>
            <div class="col-12 d-flex gap-1">
                <button class="btn btn-gc-filtro btn-outline-secondary" title="Filtrar"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('logs.index', ['limpar' => 1]) }}" class="btn btn-gc-filtro btn-light">Limpar</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Registros</strong>
            <span class="text-muted small">{{ $logs->total() }} evento(s) no filtro</span>
        </div>
        <div class="table-responsive">
            <table class="table table-gc mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width:140px">Data/hora</th>
                        <th style="width:150px">Usuário</th>
                        <th style="width:120px">Ação</th>
                        <th>Descrição</th>
                        <th style="width:120px">IP</th>
                        <th style="width:52px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="small">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td class="small">{{ $log->usuario->nome ?? '—' }}</td>
                            <td><span class="badge text-bg-light border">{{ \App\Models\LogAuditoria::rotulo($log->acao) }}</span></td>
                            <td class="small">
                                {{ $log->descricao ?: '—' }}
                                @if($log->tabela)
                                    <div class="text-muted">{{ $log->tabela }}@if($log->registro_id) #{{ $log->registro_id }}@endif</div>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $log->ip ?: '—' }}</td>
                            <td class="text-center">
                                @if($log->valores_antigos || $log->valores_novos)
                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#log-{{ $log->id }}" title="Ver o que mudou">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @if($log->valores_antigos || $log->valores_novos)
                            <tr class="collapse" id="log-{{ $log->id }}">
                                <td colspan="6" class="p-3" style="background:#F7F8FA">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:220px">Campo</th>
                                                <th>Antes</th>
                                                <th>Depois</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach(array_keys(($log->valores_novos ?? []) + ($log->valores_antigos ?? [])) as $campo)
                                                <tr>
                                                    <td class="small fw-semibold">{{ $campo }}</td>
                                                    <td class="small text-muted">{{ \Illuminate\Support\Str::limit((string) ($log->valores_antigos[$campo] ?? '—'), 120) }}</td>
                                                    <td class="small">{{ \Illuminate\Support\Str::limit((string) ($log->valores_novos[$campo] ?? '—'), 120) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @if($log->user_agent)
                                        <div class="text-muted small mt-2">{{ $log->user_agent }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6">
                            <x-empty-state icon="bi-journal-text" title="Nenhum evento encontrado"
                                           description="Ajuste os filtros — o log registra logins, criações, alterações, exclusões e exportações." />
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-footer bg-white d-flex justify-content-end">{{ $logs->links() }}</div>
        @endif
    </div>

@endsection
