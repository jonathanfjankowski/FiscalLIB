<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Resultados;

/**
 * Grupo ICMS do item (impostosV2.icms). Informe `cst` (regime normal)
 * OU `csosn` (Simples Nacional) — nunca os dois.
 */
final class IcmsResultado
{
    use Serializavel;

    public function __construct(
        public readonly int $origem = 0,
        public readonly ?string $cst = null,
        public readonly ?string $csosn = null,
        public readonly ?string $modBc = null,
        public readonly ?string $baseCalculo = null,
        public readonly ?string $aliquota = null,
        public readonly ?string $valor = null,
        public readonly ?string $percentualReducaoBc = null,
        public readonly ?string $fcpPercentual = null,
        public readonly ?string $valorFcp = null,
        /** CST 51 — diferimento */
        public readonly ?string $valorIcmsOperacao = null,
        public readonly ?string $percentualDiferimento = null,
        public readonly ?string $valorIcmsDiferido = null,
        /** CSOSN 101/201/900 — crédito SN */
        public readonly ?string $percentualCreditoSimples = null,
        public readonly ?string $valorCreditoSimples = null,
        /** Desoneração (NT 2019.001) — CST 20/40/41/70/90 com motivo informado */
        public readonly ?string $valorDesonerado = null,
        public readonly ?string $motivoDesoneracao = null,
        /** Código de benefício fiscal na UF (cBenefRBC) — item-level no XML */
        public readonly ?string $codigoBeneficioFiscal = null,
        public readonly ?IcmsStResultado $st = null,
        public readonly ?DifalResultado $difal = null,
    ) {
    }

    public function codigoTributario(): ?string
    {
        return $this->cst ?? $this->csosn;
    }

    public function isSimplesNacional(): bool
    {
        return $this->csosn !== null;
    }

    /**
     * Define o código de benefício fiscal preservando o restante do resultado
     * (construtor promotion: nomes das propriedades = nomes dos parâmetros).
     */
    public function comCodigoBeneficioFiscal(string $codigo): self
    {
        /** @var array<string, mixed> $props */
        $props = get_object_vars($this);

        return new self(...array_merge($props, ['codigoBeneficioFiscal' => $codigo]));
    }
}
