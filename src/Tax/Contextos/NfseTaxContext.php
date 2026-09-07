<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Contextos;

use FiscalLib\Common\Matematica;

/**
 * Contexto tributário de uma NFS-e (DPS). PIS/COFINS = valores DEVIDOS
 * (nunca retidos — NT 007/2026); retenções informadas à parte.
 */
final class NfseTaxContext
{
    public string $valorServicos = '0.00';
    public string $descontoIncondicionado = '0.00';
    public string $valorRecebido = '0.00';

    /** 1 tributável, 2 imunidade, 3 exportação, 4 não incidência. */
    public int $tributacaoIssqn = 1;
    /** 1 não retido, 2 retido pelo tomador, 3 retido pelo intermediário. */
    public int $retencaoIssqn = 1;
    public ?string $aliquotaIssqn = null;

    public ?string $cstPisCofins = null;
    public ?string $aliquotaPis = null;
    public ?string $aliquotaCofins = null;

    /** tpRetPisCofins: 1 nenhum, 2 PIS+COFINS, 3 CSLL (consolidado NT 007/2026). */
    public ?int $tipoRetencaoPisCofins = null;
    public string $valorRetidoCpp = '0.00';
    public string $valorRetidoIrrf = '0.00';
    public string $valorRetidoCsll = '0.00';

    public ?string $totalTributosFederal = null;
    public ?string $totalTributosEstadual = null;
    public ?string $totalTributosMunicipal = null;

    private function __construct()
    {
    }

    public static function make(): self
    {
        return new self();
    }

    public function servico(string|int|float $valorServicos, string|int|float $descontoIncondicionado = 0): self
    {
        $this->valorServicos = Matematica::escalar($valorServicos, 2);
        $this->descontoIncondicionado = Matematica::escalar($descontoIncondicionado, 2);

        return $this;
    }

    public function valorRecebido(string|int|float $valor): self
    {
        $this->valorRecebido = Matematica::escalar($valor, 2);

        return $this;
    }

    public function iss(string|int|float $aliquota, int $tributacao = 1, int $retencao = 1): self
    {
        $this->aliquotaIssqn = Matematica::normalizar($aliquota);
        $this->tributacaoIssqn = $tributacao;
        $this->retencaoIssqn = $retencao;

        return $this;
    }

    public function pisCofins(string $cst, string|int|float $aliquotaPis, string|int|float $aliquotaCofins): self
    {
        $this->cstPisCofins = $cst;
        $this->aliquotaPis = Matematica::normalizar($aliquotaPis);
        $this->aliquotaCofins = Matematica::normalizar($aliquotaCofins);

        return $this;
    }

    public function retencoes(
        ?int $tipoRetencaoPisCofins = null,
        string|int|float $cpp = 0,
        string|int|float $irrf = 0,
        string|int|float $csll = 0,
    ): self {
        $this->tipoRetencaoPisCofins = $tipoRetencaoPisCofins;
        $this->valorRetidoCpp = Matematica::escalar($cpp, 2);
        $this->valorRetidoIrrf = Matematica::escalar($irrf, 2);
        $this->valorRetidoCsll = Matematica::escalar($csll, 2);

        return $this;
    }

    public function totalTributos(string|int|float|null $federal = null, string|int|float|null $estadual = null, string|int|float|null $municipal = null): self
    {
        $this->totalTributosFederal = $federal === null ? null : Matematica::escalar($federal, 2);
        $this->totalTributosEstadual = $estadual === null ? null : Matematica::escalar($estadual, 2);
        $this->totalTributosMunicipal = $municipal === null ? null : Matematica::escalar($municipal, 2);

        return $this;
    }

    public function baseTributaria(): string
    {
        return Matematica::subtrair($this->valorServicos, $this->descontoIncondicionado, 2);
    }
}
