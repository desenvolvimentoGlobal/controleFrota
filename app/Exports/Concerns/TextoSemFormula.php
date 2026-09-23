<?php

declare(strict_types=1);

namespace App\Exports\Concerns;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Texto digitado pelo usuário (nome do serviço, fornecedor) que começa com
 * "=" seria gravado como FÓRMULA: quebra a exportação (autosize calcula) e
 * permite injetar fórmula na planilha que circula pelo financeiro. Aqui vira
 * texto puro. Use com `WithCustomValueBinder`.
 */
trait TextoSemFormula
{
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && str_starts_with($value, '=')) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return (new DefaultValueBinder)->bindValue($cell, $value);
    }
}
