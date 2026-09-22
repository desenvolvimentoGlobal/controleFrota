@extends('layouts.app')

@section('title', 'Alterar senha')

@section('content')
    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    @if(auth()->user()->deve_trocar_senha)
                        <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i> Você precisa definir uma nova senha antes de continuar.</div>
                    @endif

                    <form method="POST" action="{{ route('senha.atualizar') }}">
                        @csrf @method('PUT')

                        <div class="mb-3">
                            <label class="form-label gc-required">Senha atual</label>
                            <input type="password" name="senha_atual" class="form-control @error('senha_atual') is-invalid @enderror" autocomplete="current-password">
                            @error('senha_atual') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label gc-required">Nova senha</label>
                            <input type="password" name="senha" class="form-control @error('senha') is-invalid @enderror" autocomplete="new-password">
                            <div class="form-text">Mínimo de 8 caracteres, com letras e números.</div>
                            @error('senha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label gc-required">Confirmar nova senha</label>
                            <input type="password" name="senha_confirmation" class="form-control" autocomplete="new-password">
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            @unless(auth()->user()->deve_trocar_senha)
                                <a href="{{ route('painel') }}" class="btn btn-light">Cancelar</a>
                            @endunless
                            <button type="submit" class="btn btn-gc-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
