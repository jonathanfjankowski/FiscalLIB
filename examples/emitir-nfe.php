<?php

declare(strict_types=1);

/**
 * Exemplo — emissão NF-e completa (Regime Normal, CST 10 com ST) via FiscalAPI.
 *
 * Requer: composer install + FiscalAPI acessível em $BASE_URL (ModoSandbox=true).
 */

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\CstIcms;
use FiscalLib\Common\Enums\CstPisCofins;
use FiscalLib\Common\Enums\FormaPagamento;
use FiscalLib\Common\Enums\ModoDeterminacaoBc;
use FiscalLib\Common\Enums\OrigemMercadoria;
use FiscalLib\Common\Enums\UF;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\FiscalLib;
use FiscalLib\Tax\Contextos\NfeTaxContext;
use FiscalLib\Tax\Tabelas\ResolvedorAliquotas;

require __DIR__ . '/../vendor/autoload.php';

$lib = FiscalLib::comFiscalApi(
    FiscalConfig::criar(getenv('FISCAL_BASE_URL') ?: 'http://localhost:8080', getenv('FISCAL_API_KEY') ?: 'fk_test_...')
);

// 1) Tributos do item — a API não calcula; a lib calcula, a API valida a aritmética.
//    Alíquotas vêm do ERP (produto com alíquota diferenciada? use
//    ResolvedorAliquotas::comAliquotaInterna() para sobrescrever o default do estado).
$resolvedor = new ResolvedorAliquotas();
$aliquotaInternaSp = $resolvedor->aliquotaInternaGeral(UF::SP); // '18.00' — regra geral do estado

$tributos = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()
        ->valores(quantidade: 2, valorUnitario: 50)
        ->cfop('5102')
        ->icms(OrigemMercadoria::Nacional, CstIcms::TributadaComCobrancaIcmsPorSt, aliquota: $aliquotaInternaSp)
        ->st(ModoDeterminacaoBc::PrecoTabeladoMaximo, mva: 30, aliquotaSt: $aliquotaInternaSp)
        ->pis(CstPisCofins::OperacaoTributavelCumulativo, '1.65')
        ->cofins(CstPisCofins::OperacaoTributavelCumulativo, '7.60')
);

// 2) Documento — o builder valida as regras de negócio
$documento = $lib->nfe()->novo()
    ->ambiente(Ambiente::Homologacao)
    ->serie(1)
    ->naturezaOperacao('Venda de mercadoria')
    ->destinatario(new Destinatario(
        Cnpj::criar('11444777000161'),
        'Cliente Teste Ltda',
        endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308', uf: UF::SP),
    ))
    ->addItem(new ItemFiscal('SKU1', 'Produto de teste', '2.0000', '50.00', '100.00', $tributos, ncm: '12345678', cfop: '5102'))
    ->pagamento(FormaPagamento::Dinheiro, 100)
    ->build();

// 3) Emissão com polling até o estado terminal
$resultado = $lib->nfe()->emitir($documento);

echo "Status: {$resultado->status}" . PHP_EOL;
echo "Chave:  {$resultado->chaveAcesso}" . PHP_EOL;
echo "Motivo: {$resultado->motivoStatus}" . PHP_EOL;

if (! $resultado->isAutorizada()) {
    $resultado->exigirAutorizada(); // RejeicaoSefazException (com código extraído do motivo/XML)
}
