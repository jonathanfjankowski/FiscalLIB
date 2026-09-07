<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Resultados;

/**
 * Resultado tributário de uma NFS-e — espelha o bloco `valores` do
 * NfseDpsRequest (layout 1.01, padrão Nacional). Valores IBS/CBS NÃO são
 * enviados: na DPS só CST + cClassTrib (calculados pelo ADN).
 */
final class NfseTaxResultado
{
    use Serializavel;

    public function __construct(
        public readonly string $valorServicos,
        public readonly string $valorRecebido,
        public readonly string $descontoIncondicionado,
        public readonly int $tributacaoIssqn,
        public readonly int $retencaoIssqn,
        public readonly ?string $aliquotaIssqn,
        public readonly ?string $valorIssqn,
        public readonly ?string $cstPisCofins,
        public readonly ?string $baseCalculoPisCofins,
        public readonly ?string $aliquotaPis,
        public readonly ?string $valorPis,
        public readonly ?string $aliquotaCofins,
        public readonly ?string $valorCofins,
        public readonly ?int $tipoRetencaoPisCofins,
        public readonly string $valorRetidoCpp,
        public readonly string $valorRetidoIrrf,
        public readonly string $valorRetidoCsll,
        public readonly ?string $totalTributosFederal,
        public readonly ?string $totalTributosEstadual,
        public readonly ?string $totalTributosMunicipal,
    ) {
    }

    public function valorLiquido(): string
    {
        return \FiscalLib\Common\Matematica::subtrair(
            \FiscalLib\Common\Matematica::subtrair(
                \FiscalLib\Common\Matematica::subtrair(
                    \FiscalLib\Common\Matematica::subtrair($this->valorServicos, $this->descontoIncondicionado),
                    $this->valorIssqn ?? '0.00'
                ),
                $this->valorRetidoIrrf
            ),
            \FiscalLib\Common\Matematica::somar($this->valorRetidoCpp, $this->valorRetidoCsll)
        );
    }
}
