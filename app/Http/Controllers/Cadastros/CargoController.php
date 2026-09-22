<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cadastros;

use App\Models\Cargo;

class CargoController extends CadastroSimplesController
{
    protected string $model = Cargo::class;

    protected string $rota = 'cargos';

    protected string $singular = 'Cargo';

    protected string $artigo = 'o';

    protected function titulo(): string
    {
        return 'Cargos';
    }
}
