<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Listas fechadas das características descritivas do veículo. São opções de
 * select, não regras: por isso vivem juntas, como arrays, e não um enum cada.
 * O valor é o que vai para o banco; o rótulo é o que a tela mostra.
 */
final class CaracteristicasVeiculo
{
    public const CARROCERIAS = [
        'hatch' => 'Hatch', 'sedan' => 'Sedan', 'suv' => 'SUV', 'picape' => 'Picape',
        'minivan' => 'Minivan', 'utilitario' => 'Utilitário', 'van' => 'Van', 'outro' => 'Outro',
    ];

    public const TIPOS_COR = ['solida' => 'Sólida', 'metalica' => 'Metálica', 'perolizada' => 'Perolizada'];

    public const COMBUSTIVEIS = [
        'flex' => 'Flex (etanol/gasolina)', 'gasolina' => 'Gasolina', 'etanol' => 'Etanol',
        'diesel' => 'Diesel', 'hibrido' => 'Híbrido', 'eletrico' => 'Elétrico', 'gnv' => 'GNV',
    ];

    public const CAMBIOS = [
        'manual' => 'Manual', 'automatico' => 'Automático', 'cvt' => 'Automático CVT', 'automatizado' => 'Automatizado',
    ];

    public const TRACOES = ['dianteira' => 'Dianteira (4x2)', 'traseira' => 'Traseira (4x2)', 'integral' => 'Integral (4x4 / AWD)'];

    public const DIRECOES = ['mecanica' => 'Mecânica', 'hidraulica' => 'Hidráulica', 'eletrica' => 'Elétrica'];

    public const AR_CONDICIONADO = ['nenhum' => 'Sem ar-condicionado', 'manual' => 'Manual', 'digital' => 'Digital'];

    public const BANCOS = ['tecido' => 'Tecido', 'couro' => 'Couro', 'misto' => 'Misto'];

    /** Booleanos de conforto e segurança: coluna => rótulo. */
    public const CONFORTO = [
        'central_multimidia' => 'Central multimídia',
        'pareamento_smartphone' => 'Pareamento com smartphone',
        'painel_digital' => 'Painel digital',
        'vidros_eletricos' => 'Vidros elétricos',
        'travas_eletricas' => 'Travas elétricas',
    ];

    public const SEGURANCA = [
        'abs' => 'Freios ABS',
        'esc' => 'Controle de estabilidade (ESC)',
        'sensor_ponto_cego' => 'Sensor de ponto cego',
        'cinto_tres_pontos' => 'Cintos de três pontos',
        'isofix' => 'Fixação Isofix',
    ];

    /** @return array<int, string> */
    public static function chaves(array $lista): array
    {
        return array_keys($lista);
    }
}
