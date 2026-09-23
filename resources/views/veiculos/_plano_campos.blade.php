{{-- Campos do plano preventivo: $plano (null = novo) --}}
<div class="col-md-3">
    <label class="form-label small mb-0 gc-required">Nome</label>
    <input type="text" name="nome" value="{{ $plano->nome ?? '' }}" maxlength="120" class="form-control form-control-sm" placeholder="Troca de óleo e filtros" required>
</div>
<div class="col-md-2">
    <label class="form-label small mb-0">A cada (km)</label>
    <input type="number" name="intervalo_km" value="{{ $plano->intervalo_km ?? '' }}" min="100" class="form-control form-control-sm" placeholder="10000">
</div>
<div class="col-md-2">
    <label class="form-label small mb-0">A cada (dias)</label>
    <input type="number" name="intervalo_dias" value="{{ $plano->intervalo_dias ?? '' }}" min="1" class="form-control form-control-sm" placeholder="180">
</div>
<div class="col-md-{{ $plano ? '1' : '1' }}">
    <label class="form-label small mb-0">Último km</label>
    <input type="number" name="ultimo_km" value="{{ $plano->ultimo_km ?? '' }}" min="0" class="form-control form-control-sm" placeholder="atual">
</div>
<div class="col-md-{{ $plano ? '1' : '1' }}">
    <label class="form-label small mb-0">Última data</label>
    <input type="date" name="ultima_data" value="{{ $plano?->ultima_data?->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm">
</div>
