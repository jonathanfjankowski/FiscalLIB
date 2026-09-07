<?php

declare(strict_types=1);

/**
 * Exemplo — emissão NF-e completa (Regime Normal, CST 10 com ST) via FiscalAPI.
 *
 * Requer: composer install + FiscalAPI acessível em $BASE_URL (ModoSandbox=true).
 */

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\FiscalLib;
use FiscalLib\Tax\Contextos\NfeTaxContext;

require __DIR__ . '/../vendor/autoload.php';

$lib = FiscalLib::comFiscalApi(
    FiscalConfig::criar(getenv('FISCAL_BASE_URL') ?: 'http://localhost:8080', getenv('FISCAL_API_KEY') ?: 'fk_test_...')
);

// 1) Tributos do item — a API não calcula; a lib calcula, a API valida a aritmética
$tributos = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()
        ->valores(quantidade: 2, valorUnitario: 50)
        ->cfop('5102')
        ->icms(0, cst: '10', aliquota: 18)
        ->st(modBcSt: '4', mva: 30, aliquotaSt: 18)
        ->pis('01', '1.65')
        ->cofins('01', '7.60')
);

// 2) Documento — o builder valida as regras de negócio
$documento = $lib->nfe()->novo()
    ->ambiente(Ambiente::Homologacao)
    ->serie(1)
    ->naturezaOperacao('Venda de mercadoria')
    ->destinatario(new Destinatario(
        Cnpj::criar('11444777000161'),
        'Cliente Teste Ltda',
        endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308', uf: 'SP'),
    ))
    ->addItem(new ItemFiscal('SKU1', 'Produto de teste', '2.0000', '50.00', '100.00', $tributos, ncm: '12345678', cfop: '5102'))
    ->pagamento('01', 100)
    ->build();

// 3) Emissão com polling até o estado terminal
$resultado = $lib->nfe()->emitir($documento);

echo "Status: {$resultado->status}" . PHP_EOL;
echo "Chave:  {$resultado->chaveAcesso}" . PHP_EOL;
echo "Motivo: {$resultado->motivoStatus}" . PHP_EOL;

if (! $resultado->isAutorizada()) {
    $resultado->exigirAutorizada(); // RejeicaoSefazException (com código extraído do motivo/XML)
}
