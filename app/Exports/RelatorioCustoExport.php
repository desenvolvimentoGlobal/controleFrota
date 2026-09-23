<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Sheets\CustoManutencoesSheet;
use App\Exports\Sheets\CustoResumoSheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel do relatório de custos: aba "Resumo" (visão escolhida) e aba
 * "Manutenções" (uma linha por manutenção, para filtrar e girar tabela
 * dinâmica). Os dados vêm prontos do RelatorioCustoService.
 */
class RelatorioCustoExport implements Export, WithMultipleSheets
{
    /** @param  array<string, mixed>  $dados */
    public function __construct(private readonly array $dados) {}

    public function sheets(): array
    {
        return [
            new CustoResumoSheet($this->dados),
            new CustoManutencoesSheet($this->dados),
        ];
    }
}
