<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Contextos;

use FiscalLib\Common\Enums\OrigemMercadoria;
use FiscalLib\Common\Enums\RegimeTributario;
use FiscalLib\Common\Matematica;

/**
 * Contexto tributário de UM item de NF-e/NFC-e (fluent via make()).
 * A lib calcula e devolve valores prontos — a API valida a aritmética.
 */
final class NfeTaxContext
{
    public RegimeTributario $regimeTributario = RegimeTributario::RegimeNormal;
    public ?string $cfop = null;

    public string $valorBruto = '0.00';       // qtd × valor unitário (bruto)
    public string $valorDesconto = '0.00';    // desconto incondicionado do item
    public string $quantidade = '1.0000';
    public string $valorUnitario = '0.00';

    // ICMS
    public int $origem = 0;
    public ?string $cst = null;               // regime normal (00–90 suportados)
    public ?string $csosn = null;             // Simples Nacional (101–900)
    public ?string $modBc = null;             // default '3'
    public ?string $aliquotaIcms = null;
    public ?string $percentualReducaoBc = null;
    public ?string $aliquotaFcp = null;
    public ?string $percentualCreditoSimples = null;

    // ST própria
    public ?string $modBcSt = null;
    public ?string $percentualMva = null;
    public ?string $percentualReducaoBcSt = null;
    public ?string $aliquotaIcmsSt = null;
    public ?string $aliquotaFcpSt = null;

    // ST retida (CST 60 / CSOSN 500)
    public ?string $baseCalculoStRetida = null;
    public ?string $aliquotaStRetida = null;
    public ?string $valorStRetido = null;     // calculado se omitido
    public ?string $valorIcmsSubstituto = null;
    public ?string $fcpPercentualStRetido = null;
    public ?string $valorFcpStRetido = null;

    // CST 51
    public ?string $percentualDiferimento = null;

    // DIFAL
    public bool $difal = false;
    public ?int $aliquotaInterestadual = null;   // 4, 7 ou 12
    public ?string $aliquotaInternaUfDestino = null;
    public ?string $aliquotaFcpUfDestino = null;

    // IPI
    public ?string $cstIpi = null;
    public string $cEnqIpi = '999';
    public ?string $aliquotaIpi = null;

    // PIS/COFINS
    public ?string $cstPis = null;
    public ?string $aliquotaPis = null;
    public ?string $cstCofins = null;
    public ?string $aliquotaCofins = null;

    // Reforma
    public ?IbsCbsEntrada $ibsCbs = null;
    public ?IsEntrada $is = null;

    private function __construct()
    {
    }

    public static function make(): self
    {
        return new self();
    }

    public function regime(RegimeTributario $regime): self
    {
        $this->regimeTributario = $regime;

        return $this;
    }

    public function cfop(string $cfop): self
    {
        $this->cfop = $cfop;

        return $this;
    }

    public function valores(string|int|float $quantidade, string|int|float $valorUnitario, string|int|float $desconto = 0): self
    {
        $this->quantidade = Matematica::normalizar($quantidade);
        $this->valorUnitario = Matematica::normalizar($valorUnitario);
        $this->valorDesconto = Matematica::escalar($desconto, 2);
        $this->valorBruto = Matematica::escalar(Matematica::multiplicar($quantidade, $valorUnitario, 6), 2);

        return $this;
    }

    public function valorBruto(string|int|float $valor): self
    {
        $this->valorBruto = Matematica::escalar($valor, 2);

        return $this;
    }

    public function icms(
        string|int $origem,
        ?string $cst = null,
        ?string $csosn = null,
        string|int|float|null $aliquota = null,
        ?string $modBc = '3',
        string|int|float|null $reducaoBc = null,
        string|int|float|null $fcp = null,
    ): self {
        $this->origem = (int) $origem;
        $this->cst = $cst;
        $this->csosn = $csosn;
        $this->modBc = $modBc;
        $this->aliquotaIcms = $aliquota === null ? null : Matematica::normalizar($aliquota);
        $this->percentualReducaoBc = $reducaoBc === null ? null : Matematica::normalizar($reducaoBc);
        $this->aliquotaFcp = $fcp === null ? null : Matematica::normalizar($fcp);

        return $this;
    }

    public function st(
        string $modBcSt,
        string|int|float|null $mva = null,
        string|int|float|null $aliquotaSt = null,
        string|int|float|null $reducaoBcSt = null,
        string|int|float|null $fcpSt = null,
    ): self {
        $this->modBcSt = $modBcSt;
        $this->percentualMva = $mva === null ? null : Matematica::normalizar($mva);
        $this->aliquotaIcmsSt = $aliquotaSt === null ? null : Matematica::normalizar($aliquotaSt);
        $this->percentualReducaoBcSt = $reducaoBcSt === null ? null : Matematica::normalizar($reducaoBcSt);
        $this->aliquotaFcpSt = $fcpSt === null ? null : Matematica::normalizar($fcpSt);

        return $this;
    }

    public function stRetida(
        string|int|float $baseCalculoStRetida,
        string|int|float $aliquotaStRetida,
        string|int|float|null $valorStRetido = null,
        string|int|float|null $valorIcmsSubstituto = null,
    ): self {
        $this->baseCalculoStRetida = Matematica::normalizar($baseCalculoStRetida);
        $this->aliquotaStRetida = Matematica::normalizar($aliquotaStRetida);
        $this->valorStRetido = $valorStRetido === null ? null : Matematica::normalizar($valorStRetido);
        $this->valorIcmsSubstituto = $valorIcmsSubstituto === null ? null : Matematica::normalizar($valorIcmsSubstituto);

        return $this;
    }

    public function diferimento(string|int|float $percentual): self
    {
        $this->percentualDiferimento = Matematica::normalizar($percentual);

        return $this;
    }

    public function creditoSimples(string|int|float $percentual): self
    {
        $this->percentualCreditoSimples = Matematica::normalizar($percentual);

        return $this;
    }

    public function difalInterestadual(
        int $aliquotaInterestadual,
        string|int|float $aliquotaInternaUfDestino,
        string|int|float|null $fcpUfDestino = null,
    ): self {
        $this->difal = true;
        $this->aliquotaInterestadual = $aliquotaInterestadual;
        $this->aliquotaInternaUfDestino = Matematica::normalizar($aliquotaInternaUfDestino);
        $this->aliquotaFcpUfDestino = $fcpUfDestino === null ? null : Matematica::normalizar($fcpUfDestino);

        return $this;
    }

    public function ipi(string $cst, string|int|float|null $aliquota = null, string $cEnq = '999'): self
    {
        $this->cstIpi = $cst;
        $this->aliquotaIpi = $aliquota === null ? null : Matematica::normalizar($aliquota);
        $this->cEnqIpi = $cEnq;

        return $this;
    }

    public function pis(string $cst, ?string $aliquota = null): self
    {
        $this->cstPis = $cst;
        $this->aliquotaPis = $aliquota === null ? null : Matematica::normalizar($aliquota);

        return $this;
    }

    public function cofins(string $cst, ?string $aliquota = null): self
    {
        $this->cstCofins = $cst;
        $this->aliquotaCofins = $aliquota === null ? null : Matematica::normalizar($aliquota);

        return $this;
    }

    public function ibsCbs(IbsCbsEntrada $entrada): self
    {
        $this->ibsCbs = $entrada;

        return $this;
    }

    public function is(IsEntrada $entrada): self
    {
        $this->is = $entrada;

        return $this;
    }

    /** Base da tributação própria: bruto − desconto (− redução quando aplicável). */
    public function basePropria(): string
    {
        return Matematica::subtrair($this->valorBruto, $this->valorDesconto, 2);
    }

    public function baseComReducao(): string
    {
        $base = $this->basePropria();
        if ($this->percentualReducaoBc !== null) {
            $base = Matematica::subtrair($base, Matematica::percentualDe($base, $this->percentualReducaoBc), 2);
        }

        return $base;
    }
}
