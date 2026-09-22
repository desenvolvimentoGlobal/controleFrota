@extends('layouts.app')

@section('title', 'Novo veículo')
@section('voltar', route('veiculos.index'))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('veiculos.store') }}" enctype="multipart/form-data">
                @csrf
                @include('veiculos._form', ['veiculo' => null])

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('veiculos.index') }}" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-gc-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
