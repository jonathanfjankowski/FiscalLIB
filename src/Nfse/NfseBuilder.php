<?php

declare(strict_types=1);

namespace FiscalLib\Nfse;

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Documento\Emitente;
use FiscalLib\Documento\IbsCbsDps;
use FiscalLib\Documento\NfseDocumento;
use FiscalLib\Documento\ServicoFiscal;
use FiscalLib\Documento\Tomador;
use FiscalLib\Exceptions\ValidationException;
use FiscalLib\Tax\Resultados\NfseTaxResultado;

/**
 * Builder de NFS-e Nacional (DPS, layout 1.01).
 *
 * Regras: R-NFS001 cNBS 9 dígitos · R-NFS006 bloco IBSCBS obrigatório desde
 * 01/08/2026 · R-NFS014 exportação · endereço do tomador completo quando
 * informado (validação espelhada da API).
 */
final class NfseBuilder
{
    private Ambiente $ambiente = Ambiente::Homologacao;
    private int $serie = 1;
    private ?string $dataCompetencia = null;
    private ?int $tipoEmissor = null;
    private ?int $codigoMunicipioEmissor = null;
    private ?Tomador $tomador = null;
    private ?ServicoFiscal $servico = null;
    private ?NfseTaxResultado $tributos = null;
    private ?IbsCbsDps $ibsCbs = null;
    private ?Emitente $prestador = null;
    private ?string $informacoesComplementares = null;

    /** Obrigatório a partir de 01/08/2026 (NT 003-009) — R-NFS006. */
    public const IBSCBS_OBRIGATORIO_DESDE = '2026-08-01';

    public static function make(): self
    {
        return new self();
    }

    public function ambiente(Ambiente $ambiente): self
    {
        $this->ambiente = $ambiente;

        return $this;
    }

    public function serie(int $serie): self
    {
        $this->serie = $serie;

        return $this;
    }

    /** yyyy-MM-dd. */
    public function competencia(string $dataCompetencia): self
    {
        $this->dataCompetencia = $dataCompetencia;

        return $this;
    }

    public function tipoEmissor(int $tipoEmissor): self
    {
        $this->tipoEmissor = $tipoEmissor;

        return $this;
    }

    public function codigoMunicipioEmissor(int $codigoIbge): self
    {
        $this->codigoMunicipioEmissor = $codigoIbge;

        return $this;
    }

    public function prestador(Emitente $prestador): self
    {
        $this->prestador = $prestador;

        return $this;
    }

    public function tomador(Tomador $tomador): self
    {
        $this->tomador = $tomador;

        return $this;
    }

    public function servico(ServicoFiscal $servico): self
    {
        $this->servico = $servico;

        return $this;
    }

    public function tributos(NfseTaxResultado $tributos): self
    {
        $this->tributos = $tributos;

        return $this;
    }

    public function ibsCbs(IbsCbsDps $ibsCbs): self
    {
        $this->ibsCbs = $ibsCbs;

        return $this;
    }

    public function informacoesComplementares(string $texto): self
    {
        $this->informacoesComplementares = $texto;

        return $this;
    }

    public function build(): NfseDocumento
    {
        $erros = [];

        if ($this->serie < 1 || $this->serie > 99999) {
            $erros['serie'][] = 'Série da DPS deve estar entre 1 e 99999.';
        }

        if ($this->tipoEmissor !== null && ! in_array($this->tipoEmissor, [1, 2, 3], true)) {
            $erros['tipoEmissor'][] = 'Use 1 (prestador), 2 (tomador) ou 3 (intermediário).';
        }

        if ($this->dataCompetencia !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dataCompetencia)) {
            $erros['dataCompetencia'][] = 'Use o formato yyyy-MM-dd.';
        }

        if ($this->tomador === null) {
            $erros['tomador'][] = 'NFS-e exige tomador.';
        } else {
            $digitos = preg_replace('/\D/', '', $this->tomador->cnpjCpf()) ?? '';
            if (! in_array(strlen($digitos), [11, 14], true)) {
                $erros['tomador.cnpjCpf'][] = 'CNPJ/CPF do tomador inválido (esperado 11 ou 14 dígitos).';
            }
            $endereco = $this->tomador->endereco;
            if ($endereco !== null) {
                if ($endereco->logradouro === null || $endereco->numero === null
                    || $endereco->bairro === null || $endereco->codigoMunicipioIbge === null) {
                    $erros['tomador.endereco'][] = 'Endereço do tomador incompleto: logradouro, numero, bairro e codigoMunicipioIbge são obrigatórios.';
                } elseif (! preg_match('/^\d{7}$/', $endereco->codigoMunicipioIbge)) {
                    $erros['tomador.endereco.codigoMunicipioIbge'][] = 'Código IBGE do município deve ter 7 dígitos.';
                }
            }
        }

        if ($this->servico === null) {
            $erros['servico'][] = 'Serviço é obrigatório.';
        } else {
            if (trim($this->servico->codigoTributarioNacional) === '') {
                $erros['servico.codigoTributarioNacional'][] = 'cTribNac é obrigatório.';
            }
            if (trim($this->servico->descricaoServico) === '') {
                $erros['servico.descricaoServico'][] = 'xDescServ é obrigatório.';
            }
            if ($this->servico->codigoNbs !== null && ! preg_match('/^\d{9}$/', $this->servico->codigoNbs)) {
                $erros['servico.codigoNbs'][] = 'cNBS deve ter 9 dígitos (R-NFS001).';
            }
        }

        if ($this->tributos === null) {
            $erros['tributos'][] = 'Informe os tributos calculados (TaxEngine::calcularNfse).';
        }

        // R-NFS006 — grupo IBSCBS obrigatório desde 01/08/2026.
        $referencia = $this->dataCompetencia ?? gmdate('Y-m-d');
        if ($this->ibsCbs === null && strcmp($referencia, self::IBSCBS_OBRIGATORIO_DESDE) >= 0) {
            $erros['ibscbs'][] = 'Bloco IBSCBS obrigatório na DPS desde 01/08/2026 (R-NFS006).';
        }

        if ($this->ibsCbs !== null) {
            if (! preg_match('/^\d{6}$/', $this->ibsCbs->codigoIndicadorOperacao)) {
                $erros['ibscbs.codigoIndicadorOperacao'][] = 'cIndOp deve ter 6 dígitos (tabela SEPEC).';
            }
            if (! preg_match('/^\d{3}$/', $this->ibsCbs->cstIbsCbs)) {
                $erros['ibscbs.gibsCbs.cst'][] = 'CST do IBS/CBS deve ter 3 dígitos.';
            }
            if (! preg_match('/^\d{6}$/', $this->ibsCbs->cClassTrib)) {
                $erros['ibscbs.gibsCbs.cClassTrib'][] = 'cClassTrib deve ter 6 dígitos.';
            }
            if ($this->ibsCbs->tipoOperacaoGov !== null && $this->ibsCbs->tipoEnteGovernamental === null) {
                $erros['ibscbs.tipoEnteGovernamental'][] = 'tpOper informado exige tpEnteGov (1 União, 2 Estado, 3 DF, 4 Município).';
            }
        }

        if ($erros !== []) {
            throw new ValidationException('DPS inválida.', $erros);
        }

        \assert($this->tomador !== null && $this->servico !== null && $this->tributos !== null);

        return new NfseDocumento(
            ambiente: $this->ambiente,
            serie: $this->serie,
            tomador: $this->tomador,
            servico: $this->servico,
            tributos: $this->tributos,
            ibsCbs: $this->ibsCbs,
            dataCompetencia: $this->dataCompetencia,
            tipoEmissor: $this->tipoEmissor,
            codigoMunicipioEmissor: $this->codigoMunicipioEmissor,
            prestador: $this->prestador,
            informacoesComplementares: $this->informacoesComplementares,
        );
    }
}
