<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cadastros;

use App\Models\Setor;

class SetorController extends CadastroSimplesController
{
    protected string $model = Setor::class;

    protected string $rota = 'setores';

    protected string $singular = 'Setor';

    protected string $artigo = 'o';

    protected function titulo(): string
    {
        return 'Setores';
    }
}
