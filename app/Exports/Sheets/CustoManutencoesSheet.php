<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Exports\Concerns\TextoSemFormula;
use App\Models\Manutencao;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Aba de detalhe: uma linha por manutenção, pronta para filtro e tabela dinâmica. */
class CustoManutencoesSheet implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithStyles, WithTitle
{
    use TextoSemFormula;

    /** @param  array<string, mixed>  $dados */
    public function __construct(private readonly array $dados) {}

    public function title(): string
    {
        return 'Manutenções';
    }

    public function array(): array
    {
        $linhas = [[
            '#', 'Concluída em', 'Veículo', 'Placa', 'Tipo', 'Serviço', 'Fornecedor',
            'Previsto (R$)', 'Final (R$)', 'Diferença (R$)', 'Km na conclusão',
        ]];

        /** @var Manutencao $m */
        foreach ($this->dados['manutencoes'] as $m) {
            $previsto = $m->preco_previsto !== null ? (float) $m->preco_previsto : null;
            $final = (float) $m->preco_final;

            $linhas[] = [
                $m->id,
                $m->concluida_em->format('d/m/Y'),
                $m->veiculo->nome,
                $m->veiculo->placa,
                $m->tipo->rotulo(),
                $m->nome,
                $m->fornecedor?->nome ?? '',
                $previsto ?? '',
                $final,
                $previsto !== null ? round($final - $previsto, 2) : '',
                $m->km_conclusao,
            ];
        }

        return $linhas;
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->getStyle('H2:J'.max(2, $sheet->getHighestRow()))->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }
}
