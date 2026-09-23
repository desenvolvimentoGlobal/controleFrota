@extends('layouts.app')

@section('title', 'Nova manutenção')
@section('voltar', route('manutencoes.index'))

@section('content')
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('manutencoes.store') }}">
            @csrf
            @include('manutencoes._form', ['manutencao' => null])
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('manutencoes.index') }}" class="btn btn-light">Cancelar</a>
                <button class="btn btn-gc-primary"><i class="bi bi-check-lg"></i> Abrir manutenção</button>
            </div>
        </form>
    </div></div>
@endsection
