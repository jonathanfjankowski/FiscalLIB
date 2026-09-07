<?php

declare(strict_types=1);

/**
 * Exemplo — emissão NFS-e Nacional (DPS) com ISS retido via FiscalAPI.
 */

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\IbsCbsDps;
use FiscalLib\Documento\ServicoFiscal;
use FiscalLib\Documento\Tomador;
use FiscalLib\FiscalLib;
use FiscalLib\Tax\Contextos\NfseTaxContext;

require __DIR__ . '/../vendor/autoload.php';

$lib = FiscalLib::comFiscalApi(
    FiscalConfig::criar(getenv('FISCAL_BASE_URL') ?: 'http://localhost:8080', getenv('FISCAL_API_KEY') ?: 'fk_test_...')
);

// ISS retido + PIS/COFINS devidos (NT 007/2026: devidos ≠ retidos)
$tributos = $lib->taxEngine()->calcularNfse(
    NfseTaxContext::make()
        ->servico(5000)
        ->iss(3.0, tributacao: 1, retencao: 2)
        ->pisCofins('01', 0.65, 3.0)
        ->retencoes(tipoRetencaoPisCofins: 2, irrf: 75)
);

$documento = $lib->nfse()->novo()
    ->ambiente(Ambiente::Homologacao)
    ->serie(1)
    ->competencia('2026-09-05')
    ->tomador(new Tomador(
        Cnpj::criar('11444777000161'),
        'Cliente Serviço Ltda',
        endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308'),
    ))
    ->servico(new ServicoFiscal('010701', 'Desenvolvimento de software', codigoNbs: '112011000'))
    ->tributos($tributos)
    ->ibsCbs(new IbsCbsDps('000001', '101', '000001')) // R-NFS006: obrigatório desde 01/08/2026
    ->build();

$resultado = $lib->nfse()->emitir($documento);

echo "Status: {$resultado->status}" . PHP_EOL;
echo "Chave:  {$resultado->chaveAcesso}" . PHP_EOL; // 50 posições, prefixo NFS
echo "Líquido para o prestador: {$tributos->valorLiquido()}" . PHP_EOL;
