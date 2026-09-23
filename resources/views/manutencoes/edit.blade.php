@extends('layouts.app')

@section('title', 'Editar manutenção #'.$manutencao->id)
@section('voltar', route('manutencoes.show', $manutencao))

@section('content')
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('manutencoes.update', $manutencao) }}">
            @csrf @method('PUT')
            @include('manutencoes._form')
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('manutencoes.show', $manutencao) }}" class="btn btn-light">Cancelar</a>
                <button class="btn btn-gc-primary"><i class="bi bi-check-lg"></i> Salvar</button>
            </div>
        </form>
    </div></div>
@endsection
