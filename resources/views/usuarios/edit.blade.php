@extends('layouts.app')

@section('title', 'Editar usuário')
@section('voltar', route('usuarios.index'))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('usuarios.update', $usuario) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                @include('usuarios._form')

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('usuarios.index') }}" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-gc-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
