@php($fornecedor = $fornecedor ?? null)
@php($campo = fn (string $n, string $rotulo, array $attrs = []) => view('fornecedores._campo', ['n' => $n, 'rotulo' => $rotulo, 'attrs' => $attrs, 'fornecedor' => $fornecedor]))

<div class="row g-3">
    <div class="col-12"><h6 class="text-muted text-uppercase small mb-0">Empresa</h6></div>
    <div class="col-md-5">{{ $campo('razao_social', 'Razão social', ['required' => true]) }}</div>
    <div class="col-md-4">{{ $campo('nome_fantasia', 'Nome fantasia') }}</div>
    <div class="col-md-3">{{ $campo('cnpj', 'CNPJ', ['placeholder' => '00.000.000/0000-00', 'maxlength' => 18]) }}</div>

    <div class="col-12 mt-4"><h6 class="text-muted text-uppercase small mb-0">Contato</h6></div>
    <div class="col-md-4">{{ $campo('contato', 'Pessoa de contato') }}</div>
    <div class="col-md-3">{{ $campo('telefone', 'Telefone', ['placeholder' => '(00) 00000-0000', 'maxlength' => 15]) }}</div>
    <div class="col-md-5">{{ $campo('email', 'E-mail', ['type' => 'email']) }}</div>

    <div class="col-12 mt-4"><h6 class="text-muted text-uppercase small mb-0">Endereço</h6></div>
    <div class="col-md-2">{{ $campo('cep', 'CEP', ['id' => 'cep', 'placeholder' => '00000-000', 'maxlength' => 9]) }}</div>
    <div class="col-md-6">{{ $campo('logradouro', 'Logradouro', ['id' => 'logradouro']) }}</div>
    <div class="col-md-2">{{ $campo('numero', 'Número') }}</div>
    <div class="col-md-2">{{ $campo('complemento', 'Complemento') }}</div>
    <div class="col-md-4">{{ $campo('bairro', 'Bairro', ['id' => 'bairro']) }}</div>
    <div class="col-md-4">{{ $campo('cidade', 'Cidade', ['id' => 'cidade']) }}</div>
    <div class="col-md-2">
        <label class="form-label">UF</label>
        <select name="uf" id="uf" class="form-select @error('uf') is-invalid @enderror">
            <option value="">—</option>
            @foreach($ufs as $uf)
                <option value="{{ $uf }}" @selected(old('uf', $fornecedor->uf ?? '') === $uf)>{{ $uf }}</option>
            @endforeach
        </select>
        @error('uf') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label">Observações</label>
        <textarea name="observacoes" rows="3" maxlength="2000" class="form-control @error('observacoes') is-invalid @enderror">{{ old('observacoes', $fornecedor->observacoes ?? '') }}</textarea>
        @error('observacoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @if($fornecedor)
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1" @checked(old('ativo', $fornecedor->ativo))>
                <label class="form-check-label" for="ativo">Fornecedor ativo</label>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.getElementById('cep')?.addEventListener('blur', async function () {
        const cep = this.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        try {
            const dados = await (await fetch(`https://viacep.com.br/ws/${cep}/json/`)).json();
            if (dados.erro) return;
            const p = (id, v) => { const c = document.getElementById(id); if (c && !c.value) c.value = v || ''; };
            p('logradouro', dados.logradouro); p('bairro', dados.bairro); p('cidade', dados.localidade);
            const uf = document.getElementById('uf'); if (uf && !uf.value) uf.value = dados.uf || '';
        } catch (e) {}
    });
</script>
@endpush
