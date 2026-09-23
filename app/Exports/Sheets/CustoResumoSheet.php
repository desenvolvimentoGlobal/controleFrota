<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Exports\Concerns\TextoSemFormula;
use App\Support\Numero;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Aba de resumo: cabeçalho de contexto + tabela da visão escolhida. */
class CustoResumoSheet implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithStyles, WithTitle
{
    use TextoSemFormula;

    /** Linha do cabeçalho da tabela (depois das linhas de contexto). */
    private const LINHA_CABECALHO = 10;

    /** @param  array<string, mixed>  $dados */
    public function __construct(private readonly array $dados) {}

    public function title(): string
    {
        return 'Resumo';
    }

    public function array(): array
    {
        $r = $this->dados['resumo'];
        $porVeiculo = $this->dados['visao'] === 'veiculo';

        // A planilha circula solta: o contexto vai junto com os números.
        $linhas = [
            ['Custos de manutenção — '.$this->dados['visaoLabel']],
            ['Período (conclusão)', $this->dados['periodoLabel']],
            ['Filtros', $this->dados['filtrosLabel']],
            ['Base', 'Manutenções prestadas no período, pelo preço final'],
            ['Total realizado (R$)', $r['total']],
            ['Manutenções', $r['quantidade']],
            ['Comprometido em aberto', Numero::moeda($r['comprometido']).' ('.$r['comprometido_qtd'].' abertas, pelo previsto)'],
            ['Gerado em', now()->format('d/m/Y H:i')],
            [],
            array_merge(
                [$this->dados['visaoLabel'], 'Manutenções', 'Custo (R$)', 'Média (R$)', '% do total'],
                $porVeiculo ? ['Km rodados', 'Custo por km (R$)'] : [],
            ),
        ];

        foreach ($this->dados['linhas'] as $l) {
            $linhas[] = array_merge(
                [$l['rotulo'].($l['detalhe'] ? " ({$l['detalhe']})" : ''), $l['quantidade'], round($l['custo'], 2), round($l['media'], 2), round($l['percentual'], 1)],
                $porVeiculo ? [$l['km'], $l['custo_km'] !== null ? round($l['custo_km'], 2) : ''] : [],
            );
        }

        $linhas[] = array_merge(
            ['TOTAL', $r['quantidade'], round($r['total'], 2), round($r['media'], 2), $r['total'] > 0 ? 100 : 0],
            $porVeiculo ? [$r['km'], $r['custo_km'] !== null ? round($r['custo_km'], 2) : ''] : [],
        );

        return $linhas;
    }

    public function styles(Worksheet $sheet): ?array
    {
        $ultima = $sheet->getHighestRow();
        $sheet->getStyle('C'.self::LINHA_CABECALHO.':D'.$ultima)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('G'.self::LINHA_CABECALHO.':G'.$ultima)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('B5')->getNumberFormat()->setFormatCode('#,##0.00');

        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            self::LINHA_CABECALHO => ['font' => ['bold' => true]],
            $ultima => ['font' => ['bold' => true]],
        ];
    }
}
