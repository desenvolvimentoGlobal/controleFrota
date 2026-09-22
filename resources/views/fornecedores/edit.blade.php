@extends('layouts.app')

@section('title', 'Editar fornecedor')
@section('voltar', route('fornecedores.index'))

@section('content')
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('fornecedores.update', $fornecedor) }}">
            @csrf @method('PUT')
            @include('fornecedores._form')
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('fornecedores.index') }}" class="btn btn-light">Cancelar</a>
                <button class="btn btn-gc-primary"><i class="bi bi-check-lg"></i> Salvar</button>
            </div>
        </form>
    </div></div>
@endsection
