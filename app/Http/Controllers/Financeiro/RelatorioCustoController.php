<?php

declare(strict_types=1);

namespace App\Http\Controllers\Financeiro;

use App\Enums\TipoManutencao;
use App\Exports\RelatorioCustoExport;
use App\Http\Controllers\Concerns\FiltrosPersistentes;
use App\Http\Controllers\Controller;
use App\Models\Fornecedor;
use App\Models\Veiculo;
use App\Services\AuditoriaService;
use App\Services\RelatorioCustoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relatório de custos de manutenção (admin e financeiro, gate `financeiro.ver`).
 * Tela, PDF e Excel usam o mesmo RelatorioCustoService.
 */
class RelatorioCustoController extends Controller
{
    use FiltrosPersistentes;

    private const FILTROS = ['de', 'ate', 'veiculo_id', 'fornecedor_id', 'tipo', 'visao'];

    public function __construct(
        private readonly RelatorioCustoService $servico,
        private readonly AuditoriaService $auditoria,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirecionar = $this->filtrosPersistentes($request, self::FILTROS)) {
            return $redirecionar;
        }

        return view('relatorios.custos', $this->servico->gerar($request->only(self::FILTROS)) + [
            'visoes' => RelatorioCustoService::VISOES,
            'tipos' => TipoManutencao::paraSelect(),
            'veiculos' => Veiculo::withTrashed()->orderBy('nome')->get(['id', 'nome', 'placa']),
            'fornecedores' => Fornecedor::withTrashed()->orderBy('razao_social')->get(['id', 'razao_social', 'nome_fantasia']),
        ]);
    }

    public function pdf(Request $request): Response
    {
        $dados = $this->servico->gerar($request->only(self::FILTROS));
        $this->auditar('PDF', $dados);

        return Pdf::loadView('relatorios.pdf.custos', $dados)
            ->setPaper('a4', 'portrait')
            // Sem subconjunto, a DejaVu inteira vai embutida (~900 KB por PDF).
            ->setOption('isFontSubsettingEnabled', true)
            ->download($this->nomeArquivo($dados).'.pdf');
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $dados = $this->servico->gerar($request->only(self::FILTROS));
        $this->auditar('Excel', $dados);

        return Excel::download(new RelatorioCustoExport($dados), $this->nomeArquivo($dados).'.xlsx');
    }

    /** @param  array<string, mixed>  $dados */
    private function nomeArquivo(array $dados): string
    {
        return 'custos-manutencao-'.$dados['visao'].'-'.$dados['filtros']['de']->format('Ymd').'-'.$dados['filtros']['ate']->format('Ymd');
    }

    /** Exportar tira os números do sistema: fica na trilha de auditoria. */
    private function auditar(string $formato, array $dados): void
    {
        $this->auditoria->registrar(
            acao: 'exportou',
            modulo: 'relatorios',
            descricao: "Exportou em {$formato} os custos de manutenção — {$dados['visaoLabel']}, {$dados['periodoLabel']}",
            valoresNovos: $dados['filtrosQuery'],
        );
    }
}
