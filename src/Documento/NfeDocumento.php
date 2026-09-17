<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\FinalidadeNfe;
use FiscalLib\Common\Enums\IndicadorConsumidorFinal;
use FiscalLib\Common\Enums\IndicadorPresenca;
use FiscalLib\Common\Enums\ModeloDocumento;
use FiscalLib\Common\Enums\TipoOperacao;

/**
 * MODELO intermediário de NF-e (55) / NFC-e (65) — a "língua franca" da lib.
 * Builders populam; emissores (EmissorInterface) consomem. Nada aqui conhece HTTP.
 */
final class NfeDocumento
{
    /**
     * @param list<ItemFiscal>       $itens
     * @param list<Pagamento>        $pagamentos
     * @param list<NfeReferenciada>  $nfesReferenciadas
     */
    public function __construct(
        public readonly ModeloDocumento $modelo,
        public readonly Ambiente $ambiente,
        public readonly int $serie,
        public readonly array $itens,
        public readonly TotaisDocumento $totais,
        public readonly ?string $naturezaOperacao = null,
        public readonly FinalidadeNfe $finalidade = FinalidadeNfe::Normal,
        public readonly TipoOperacao $tipoOperacao = TipoOperacao::Saida,
        public readonly ?IndicadorPresenca $indicadorPresenca = null,
        public readonly IndicadorConsumidorFinal $indicadorConsumidorFinal = IndicadorConsumidorFinal::Sim,
        public readonly ?Destinatario $destinatario = null,
        public readonly ?Emitente $emitente = null,
        public readonly array $pagamentos = [],
        public readonly array $nfesReferenciadas = [],
        public readonly ?string $informacoesComplementares = null, // ignorado pela FiscalAPI; útil p/ outros emissores
        public readonly ?int $indicadorIntermediador = null,  // NT 2020.006 (indIntermed): 0=sem intermediador, 1=plataforma de terceiros — só NF-e (55)
        public readonly ?string $cnpjIntermediador = null,    // obrigatório quando indicadorIntermediador = 1
    ) {
    }
}
