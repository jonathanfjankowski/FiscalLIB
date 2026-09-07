# FiscalLIB (fiscal-lib/fiscal-lib)

Biblioteca fiscal PHP 8.1+ para **NF-e (55)**, **NFC-e (65)** e **NFS-e Nacional (DPS)**.
Ela **calcula tributos, aplica regras de negócio e monta o documento**; a transmissão
(assinatura, SEFAZ, contingência, PDF) fica a cargo do **emissor plugado**.

## Arquitetura

```
ERP ──► TaxEngine (cálculo puro) ──► Builders (regras) ──► Documento/ (modelo tipado)
                                                              │
                                                     EmissorInterface (porta)
                                              ┌──────────────┴──────────────┐
                                   Adapters/FiscalApi          SUA implementação
                                   (FiscalAPI, embutida)       (outra API/SEFAZ)
```

- **Núcleo sem I/O**: nada do núcleo conhece HTTP. O adaptador FiscalAPI é opcional e substituível.
- **A API não calcula tributos** — os valores chegam prontos; a FiscalAPI valida a aritmética (tolerância R$ 0,01).
- **Aritmética espelha os validadores da FiscalAPI** (`ValidadorImpostosV2`): todo valor é `base × alíquota / 100` com arredondamento bancário.

## Instalação

```bash
composer require jonathanfjankowski/fiscal-lib
```

## Início rápido — NF-e (Regime Normal, CST 10 com ST)

```php
use FiscalLib\Config\FiscalConfig;
use FiscalLib\FiscalLib;
use FiscalLib\Tax\Contextos\NfeTaxContext;

$lib = FiscalLib::comFiscalApi(FiscalConfig::criar('https://sua-fiscalapi', 'fk_test_...'));

// 1) Calcula os tributos do item (por fora: IBS/CBS somam no total)
$tributos = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()
        ->valores(quantidade: 2, valorUnitario: 50, desconto: 0)
        ->cfop('5102')
        ->icms(0, cst: '10', aliquota: 18)
        ->st(modBcSt: '4', mva: 30, aliquotaSt: 18)
        ->ipi('50', aliquota: 10)
        ->pis('01', '1.65')
        ->cofins('01', '7.60')
);

// 2) Monta o documento (builder valida as regras)
$documento = $lib->nfe()->novo()
    ->serie(1)
    ->naturezaOperacao('Venda de mercadoria')
    ->addItem(new ItemFiscal('SKU1', 'Produto', '2.0000', '50.00', '100.00', $tributos, cfop: '5102'))
    ->pagamento('01', 100)
    ->build();

// 3) Emite e aguarda o estado terminal (polling 2s→5s→10s→…)
$resultado = $lib->nfe()->emitir($documento);

if ($resultado->isAutorizada()) {
    // persista chaveAcesso, protocoloAutorizacao, xmlAssinado, xmlRetornoSefaz
}

// Rejeição não lança — se preferir exceção:
$resultado->exigirAutorizada(); // lança RejeicaoSefazException com código extraído
```

## NFS-e Nacional (DPS)

```php
$tributos = $lib->taxEngine()->calcularNfse(
    NfseTaxContext::make()->servico(5000)->iss(3.0, retencao: 2)->pisCofins('01', 0.65, 3.0)
);

$documento = $lib->nfse()->novo()
    ->serie(1)
    ->competencia('2026-09-05')
    ->tomador($tomador)                      // Documento\Tomador
    ->servico(new ServicoFiscal('010701', 'Desenvolvimento de software', codigoNbs: '112011000'))
    ->tributos($tributos)
    ->ibsCbs(new IbsCbsDps('000001', '101', '000001')) // obrigatório desde 01/08/2026
    ->build();

$resultado = $lib->nfse()->emitir($documento);
```

## Eventos, PDF e baixo nível

```php
$lib->eventos()->cancelar($documentoId, 'Pedido cancelado pelo cliente');     // 15–1000 chars
$lib->eventos()->cartaCorrecao($documentoId, 'Corrige o frete do item 1');    // só NF-e
$lib->eventos()->inutilizar(new InutilizacaoPedido(Ambiente::Producao, ModeloDocumento::Nfe, 1, 10, 12, 'Numeracao pulada'));
$lib->nfe()->baixarPdf($documentoId);

// Fluxo assíncrono (filas/workers): só o aceite; polling depois
$aceite = $lib->nfe()->emitirAsync($documento, OpcoesEmissao::comIdempotencia($chaveDoPedido));
// ... persista $aceite->documentoId, então:
$resultado = $lib->aguardarTerminal($aceite->documentoId);
```

**Idempotência**: gere a key quando o pedido entrar no faturamento e **reutilize em qualquer retry** — trocar a key pode duplicar documentos e consumir números.

## Gestão (só no adaptador FiscalAPI)

```php
$lib->gestao()?->enviarCertificado('certificado.pfx', $senha);
$lib->gestao()?->statusServico(ModeloDocumento::Nfe, Ambiente::Homologacao);
$lib->gestao()?->atualizarPerfil(['inscricaoMunicipal' => '123456', 'csc' => '...', 'cscId' => '1']);
$lib->gestao()?->notasRecebidas(); // Distribuição DFe + manifestação
```

## Webhook (validação da entrega)

```php
// No endpoint do ERP que recebe a notificação:
VerificadorAssinaturaHmac::validar($corpoBruto, $_SERVER['HTTP_X_FISCAL_SIGNATURE'], $segredo);
```

## Usar OUTRO emissor (outra API / SEFAZ direta)

Implemente a porta `FiscalLib\Contracts\EmissorInterface` recebendo os modelos
tipados de `FiscalLib\Documento\` e injete-a:

```php
$lib = new FiscalLib(new MeuEmissorDireto(), $config);
$lib->nfe()->emitir($documento); // mesmo fluxo, outro transporte
```

O contrato está documentado em `docs/payload-contract.md` e exemplificado por
`src/Adapters/FiscalApi/EmissorFiscalApi.php`.

## Integração Laravel (opcional)

```bash
php artisan vendor:publish --tag=fiscal-lib-config   # publica config/fiscal-lib.php
```
Registre `FiscalLib\Laravel\FiscalLibServiceProvider` e use `app('fiscal-lib')`.

## Desenvolvimento

```bash
composer install
composer test   # PHPUnit (84 testes)
composer stan   # PHPStan nível 5
```

## Licença

MIT.
