@extends('layouts.app')

@section('title', 'Editar veículo')
@section('voltar', route('veiculos.show', $veiculo))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('veiculos.update', $veiculo) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                @include('veiculos._form')

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('veiculos.show', $veiculo) }}" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-gc-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
