@php($usuario = $usuario ?? null)
<div class="row g-3">

    <div class="col-12"><h6 class="text-muted text-uppercase small mb-0">Dados de acesso</h6></div>

    <div class="col-md-6">
        <label class="form-label gc-required">Nome</label>
        <input type="text" name="nome" value="{{ old('nome', $usuario->nome ?? '') }}" class="form-control @error('nome') is-invalid @enderror">
        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label gc-required">Login</label>
        <input type="text" name="login" value="{{ old('login', $usuario->login ?? '') }}" maxlength="50" class="form-control @error('login') is-invalid @enderror" placeholder="nome.sobrenome">
        @error('login') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label gc-required">Perfil</label>
        <select name="perfil_id" class="form-select @error('perfil_id') is-invalid @enderror">
            <option value="">Selecione</option>
            @foreach($perfis as $perfil)
                <option value="{{ $perfil->id }}" @selected(old('perfil_id', $usuario->perfil_id ?? '') == $perfil->id)>{{ $perfil->nome }}</option>
            @endforeach
        </select>
        @error('perfil_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label gc-required">E-mail</label>
        <input type="email" name="email" value="{{ old('email', $usuario->email ?? '') }}" class="form-control @error('email') is-invalid @enderror">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label gc-required">CPF</label>
        <input type="text" name="cpf" value="{{ old('cpf', $usuario->cpf_formatado ?? '') }}" maxlength="14" class="form-control @error('cpf') is-invalid @enderror" placeholder="000.000.000-00">
        @error('cpf') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-5">
        <label class="form-label {{ $usuario ? '' : 'gc-required' }}">Senha {{ $usuario ? '(em branco mantém a atual)' : '' }}</label>
        <input type="password" name="senha" class="form-control @error('senha') is-invalid @enderror" autocomplete="new-password">
        <div class="form-text">Mínimo de 8 caracteres, com letras e números.</div>
        @error('senha') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="deve_trocar_senha" id="deve_trocar_senha" value="1" @checked(old('deve_trocar_senha', $usuario->deve_trocar_senha ?? ! $usuario))>
            <label class="form-check-label" for="deve_trocar_senha">Exigir troca de senha no próximo acesso</label>
        </div>
    </div>

    <div class="col-12 mt-4"><h6 class="text-muted text-uppercase small mb-0">Organização</h6></div>

    <div class="col-md-4">
        <label class="form-label">Setor</label>
        <select name="setor_id" class="form-select @error('setor_id') is-invalid @enderror">
            <option value="">—</option>
            @foreach($setores as $setor)
                <option value="{{ $setor->id }}" @selected(old('setor_id', $usuario->setor_id ?? '') == $setor->id)>{{ $setor->nome }}</option>
            @endforeach
        </select>
        @error('setor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Cargo</label>
        <select name="cargo_id" class="form-select @error('cargo_id') is-invalid @enderror">
            <option value="">—</option>
            @foreach($cargos as $cargo)
                <option value="{{ $cargo->id }}" @selected(old('cargo_id', $usuario->cargo_id ?? '') == $cargo->id)>{{ $cargo->nome }}</option>
            @endforeach
        </select>
        @error('cargo_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Gestor responsável</label>
        <select name="gestor_id" class="form-select @error('gestor_id') is-invalid @enderror">
            <option value="">—</option>
            @foreach($gestores as $gestor)
                <option value="{{ $gestor->id }}" @selected(old('gestor_id', $usuario->gestor_id ?? '') == $gestor->id)>{{ $gestor->nome }}</option>
            @endforeach
        </select>
        <div class="form-text">Define quem aprova as alocações e enxerga este usuário na visão de gestor.</div>
        @error('gestor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 mt-4"><h6 class="text-muted text-uppercase small mb-0">Habilitação</h6></div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="pode_dirigir" id="pode_dirigir" value="1" @checked(old('pode_dirigir', $usuario->pode_dirigir ?? false))>
            <label class="form-check-label" for="pode_dirigir">Pode dirigir veículos da frota</label>
        </div>
        <div class="form-text">A CNH não é obrigatória para alocar. Sem CNH ou com CNH vencida, o sistema apenas avisa.</div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Número da CNH</label>
        <input type="text" name="cnh_numero" value="{{ old('cnh_numero', $usuario->cnh_numero ?? '') }}" maxlength="20" class="form-control @error('cnh_numero') is-invalid @enderror">
        @error('cnh_numero') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Categoria</label>
        <select name="cnh_categoria" class="form-select @error('cnh_categoria') is-invalid @enderror">
            <option value="">—</option>
            @foreach($categoriasCnh as $categoria)
                <option value="{{ $categoria }}" @selected(old('cnh_categoria', $usuario->cnh_categoria ?? '') === $categoria)>{{ $categoria }}</option>
            @endforeach
        </select>
        @error('cnh_categoria') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Validade</label>
        <input type="date" name="cnh_validade" value="{{ old('cnh_validade', $usuario?->cnh_validade?->toDateString()) }}" class="form-control @error('cnh_validade') is-invalid @enderror">
        @error('cnh_validade') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 mt-4"><h6 class="text-muted text-uppercase small mb-0">Contato</h6></div>

    <div class="col-md-3">
        <label class="form-label">Telefone</label>
        <input type="text" name="telefone" value="{{ old('telefone', $usuario->telefone ?? '') }}" maxlength="15" class="form-control @error('telefone') is-invalid @enderror" placeholder="(00) 0000-0000">
        @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Celular</label>
        <input type="text" name="celular" value="{{ old('celular', $usuario->celular ?? '') }}" maxlength="15" class="form-control @error('celular') is-invalid @enderror" placeholder="(00) 00000-0000">
        @error('celular') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Foto</label>
        <input type="file" name="foto" accept="image/*" class="form-control @error('foto') is-invalid @enderror">
        @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @if($usuario?->foto_path)
            <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" name="remover_foto" id="remover_foto" value="1">
                <label class="form-check-label small" for="remover_foto">Remover a foto atual</label>
            </div>
        @endif
    </div>

    <div class="col-12 mt-4"><h6 class="text-muted text-uppercase small mb-0">Endereço</h6></div>

    <div class="col-md-2">
        <label class="form-label">CEP</label>
        <input type="text" name="cep" id="cep" value="{{ old('cep', $usuario->cep ?? '') }}" maxlength="9" class="form-control @error('cep') is-invalid @enderror" placeholder="00000-000">
        @error('cep') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Logradouro</label>
        <input type="text" name="logradouro" id="logradouro" value="{{ old('logradouro', $usuario->logradouro ?? '') }}" class="form-control @error('logradouro') is-invalid @enderror">
        @error('logradouro') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">Número</label>
        <input type="text" name="numero" value="{{ old('numero', $usuario->numero ?? '') }}" maxlength="20" class="form-control @error('numero') is-invalid @enderror">
        @error('numero') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">Complemento</label>
        <input type="text" name="complemento" value="{{ old('complemento', $usuario->complemento ?? '') }}" maxlength="100" class="form-control @error('complemento') is-invalid @enderror">
        @error('complemento') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Bairro</label>
        <input type="text" name="bairro" id="bairro" value="{{ old('bairro', $usuario->bairro ?? '') }}" maxlength="100" class="form-control @error('bairro') is-invalid @enderror">
        @error('bairro') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Cidade</label>
        <input type="text" name="cidade" id="cidade" value="{{ old('cidade', $usuario->cidade ?? '') }}" maxlength="100" class="form-control @error('cidade') is-invalid @enderror">
        @error('cidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">UF</label>
        <select name="uf" id="uf" class="form-select @error('uf') is-invalid @enderror">
            <option value="">—</option>
            @foreach($ufs as $uf)
                <option value="{{ $uf }}" @selected(old('uf', $usuario->uf ?? '') === $uf)>{{ $uf }}</option>
            @endforeach
        </select>
        @error('uf') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @if($usuario)
        <div class="col-12 mt-4">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1" @checked(old('ativo', $usuario->ativo))>
                <label class="form-check-label" for="ativo">Usuário ativo</label>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    // Preenche o endereço a partir do CEP (ViaCEP). Falha em silêncio.
    document.getElementById('cep')?.addEventListener('blur', async function () {
        const cep = this.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        try {
            const resp = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const dados = await resp.json();
            if (dados.erro) return;
            const preencher = (id, valor) => { const c = document.getElementById(id); if (c && !c.value) c.value = valor || ''; };
            preencher('logradouro', dados.logradouro);
            preencher('bairro', dados.bairro);
            preencher('cidade', dados.localidade);
            const uf = document.getElementById('uf');
            if (uf && !uf.value) uf.value = dados.uf || '';
        } catch (e) { /* offline — segue manual */ }
    });
</script>
@endpush
