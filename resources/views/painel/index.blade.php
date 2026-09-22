@extends('layouts.app')

@section('title', 'Painel')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-people" label="Usuários ativos" :value="$indicadores['usuarios_ativos']" tone="navy"
                         :href="auth()->user()->can('usuarios.gerenciar') ? route('usuarios.index') : null" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-person-badge" label="Aptos a dirigir" :value="$indicadores['motoristas']" tone="teal" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-card-checklist" label="CNH vencendo em 30 dias" :value="$indicadores['cnh_vencendo']"
                         :tone="$indicadores['cnh_vencendo'] > 0 ? 'amber' : 'green'" />
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <x-empty-state icon="bi-car-front" title="Frota, alocações e manutenções chegam nas próximas fases"
                           description="Esta é a fase 0: acesso, perfis, usuários, notificações e auditoria. Os indicadores de veículos aparecerão aqui a partir da fase 1." />
        </div>
    </div>
@endsection
