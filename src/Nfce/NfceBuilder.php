<?php

declare(strict_types=1);

namespace FiscalLib\Nfce;

use FiscalLib\Common\Enums\FinalidadeNfe;
use FiscalLib\Common\Enums\IndicadorConsumidorFinal;
use FiscalLib\Common\Enums\IndicadorPresenca;
use FiscalLib\Common\Enums\ModeloDocumento;
use FiscalLib\Common\Matematica;
use FiscalLib\Documento\TotaisDocumento;
use FiscalLib\Exceptions\ValidationException;
use FiscalLib\Nfe\NfeBuilder;

/**
 * Builder de NFC-e (modelo 65). Herda tudo do NfeBuilder; diferenças:
 * destinatário opcional (vNF ≤ 10.000 sem identificação), pagamento
 * obrigatório, sem IPI/PIS/COFINS, indPres presencial/delivery.
 *
 * R-NFC001 vNF ≤ 200.000 · R-NFC002 sem dest. ≤ 10.000 · R-NFC005 indPres
 */
final class NfceBuilder extends NfeBuilder
{
    public function __construct()
    {
        $this->indicadorPresenca = IndicadorPresenca::Presencial;
    }

    public static function nfce(): self
    {
        return new self();
    }

    public function finalidade(FinalidadeNfe $finalidade): static
    {
        if ($finalidade !== FinalidadeNfe::Normal) {
            throw ValidationException::erro('finalidade', 'NFC-e só admite finalidade normal (R-NFC007 — para correção, cancele e reemita).');
        }

        return parent::finalidade($finalidade);
    }

    public function consumidorFinal(IndicadorConsumidorFinal $consumidorFinal): static
    {
        if ($consumidorFinal !== IndicadorConsumidorFinal::Sim) {
            throw ValidationException::erro('indicadorConsumidorFinal', 'NFC-e é sempre operação com consumidor final.');
        }

        return parent::consumidorFinal($consumidorFinal);
    }

    protected function modeloDocumento(): ModeloDocumento
    {
        return ModeloDocumento::Nfce;
    }

    protected function validarEspecifico(TotaisDocumento $totais): void
    {
        $erros = [];

        if (bccomp($totais->valorNota, '200000.00', 2) === 1) {
            $erros['totais.valorNota'][] = 'NFC-e não pode exceder R$ 200.000,00 (R-NFC001).';
        }

        if ($this->destinatario === null && bccomp($totais->valorNota, '10000.00', 2) === 1) {
            $erros['totais.valorNota'][] = 'NFC-e sem destinatário identificado limitada a R$ 10.000,00 (R-NFC002).';
        }

        foreach ($this->itens as $i => $item) {
            if ($item->tributos?->ipi !== null) {
                $erros["itens[{$i}].impostosV2.ipi"][] = 'NFC-e não admite IPI (R-NFC004).';
            }
        }

        $erros = array_merge($erros, self::validarPagamentosNfce($totais, $this->pagamentos));

        if ($this->indicadorPresenca !== null
            && ! in_array($this->indicadorPresenca, [IndicadorPresenca::Presencial, IndicadorPresenca::EntregaDomicilio], true)) {
            $erros['indicadorPresenca'][] = 'NFC-e admite apenas indPres presencial ou entrega em domicílio (R-NFC005).';
        }

        if ($erros !== []) {
            throw new ValidationException('NFC-e inválida.', $erros);
        }
    }
}
