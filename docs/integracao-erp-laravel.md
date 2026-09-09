# Integração ERP Laravel ↔ fiscal-lib

Guia de integração da biblioteca `jonathanfjankowski/fiscal-lib` com um ERP em Laravel:
do `composer require` até a emissão de NF-e autorizada, eventos de pós-emissão e webhooks.

Escopo: a lib **calcula tributos, aplica regras de negócio e monta o documento**; a
transmissão (assinatura XML, SEFAZ, contingência, PDF, numeração) é feita pelo emissor
plugado — neste guia, o adaptador FiscalAPI embutido (`FiscalLib\Adapters\FiscalApi\EmissorFiscalApi`).
Tudo que os exemplos usam existe no código-fonte em `src/`; nada aqui é API imaginada.

Documentos complementares:

- [fiscal-rules.md](fiscal-rules.md) — regras e fórmulas do motor tributário (`Tax\TaxEngine`).
- [payload-contract.md](payload-contract.md) — contrato interno `Documento\*`, JSON enviado à FiscalAPI e como implementar outros emissores.
- [../README.md](../README.md) — visão geral e início rápido.
- [../examples/emitir-nfe.php](../examples/emitir-nfe.php) — script de exemplo executável (PHP puro).

---

## 1. Requisitos e instalação do pacote

### 1.1 Requisitos (conforme `composer.json`)

| Requisito | Versão |
|---|---|
| PHP | `^8.1` |
| Extensões | `ext-bcmath`, `ext-json` |
| HTTP | `guzzlehttp/guzzle` `^7.8` |
| PSR | `psr/http-client` `^1.0`, `psr/http-factory` `^1.0`, `psr/http-message` `^1.1` |
| Opcional (Laravel) | `illuminate/support` (sugerido pelo pacote para ServiceProvider/Facade) |

Em um projeto Laravel `illuminate/support` já está instalado — não é preciso exigir de novo.

### 1.2 Instalação via repositório local (path repository)

O pacote está em `C:\Projetos\Pessoal\FiscalLIB` (git, tag `v0.1.0`). No `composer.json`
do ERP, adicione o repositório e exija o pacote:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "C:\\Projetos\\Pessoal\\FiscalLIB"
        }
    ],
    "require": {
        "jonathanfjankowski/fiscal-lib": "*"
    }
}
```

```bash
composer update jonathanfjankowski/fiscal-lib
```

Com `type: "path"` o Composer resolve a versão a partir do git do repositório
(atualmente `0.1.0`, tag `v0.1.0`) e faz symlink — alterações na lib aparecem
imediatamente no ERP (útil durante a integração).

### 1.3 Instalação via VCS git (equipe/CI)

Se a lib estiver hospedada num servidor git acessível (GitHub, GitLab, Bitbucket etc.):

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://seu-servidor-git/usuario/fiscal-lib.git"
        }
    ],
    "require": {
        "jonathanfjankowski/fiscal-lib": "^0.1.0"
    }
}
```

### 1.4 Instalação via Packagist (caminho futuro)

Quando o pacote for publicado no Packagist, nenhum bloco `repositories` será necessário:

```bash
composer require jonathanfjankowski/fiscal-lib
```

> Limitação conhecida: no momento o pacote **não** está publicado no Packagist;
> use path ou VCS (seções 1.2/1.3). Não existe também um servidor git remoto
> configurado no repositório local — publique-o antes de usar a opção 1.3.

---

## 2. Instalação no Laravel

### 2.1 Registro do ServiceProvider

O `composer.json` da lib **não** declara auto-discovery (não há seção `extra`), então
registre o provider manualmente.

Laravel 11+ (`bootstrap/providers.php`):

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    FiscalLib\Laravel\FiscalLibServiceProvider::class,
];
```

Laravel 10 ou anterior (`config/app.php`):

```php
'providers' => [
    // ...
    FiscalLib\Laravel\FiscalLibServiceProvider::class,
],
```

O provider (`FiscalLib\Laravel\FiscalLibServiceProvider`) faz, no `register()`:

1. `mergeConfigFrom(... , 'fiscal-lib')` — mescla o config padrão;
2. registra o singleton de container `fiscal-lib`, que devolve uma instância de `FiscalLib\FiscalLib` criada via `FiscalLib::comFiscalApi(...)`;
3. cria o alias `FiscalLib::class` → `'fiscal-lib'`, permitindo injeção por type-hint de `FiscalLib`.

No `boot()` publica o config com a tag `fiscal-lib-config`.

### 2.2 Publicação do config

```bash
php artisan vendor:publish --tag=fiscal-lib-config
```

Isso copia `config/fiscal-lib.php` para o projeto. Conteúdo real do arquivo publicado:

```php
<?php

return [
    'base_url' => env('FISCAL_API_BASE_URL', 'http://localhost:8080'),
    'api_key' => env('FISCAL_API_KEY'),
    // producao|homologacao — deve bater com o ambiente da API key (fk_live_/fk_test_)
    'ambiente' => env('FISCAL_AMBIENTE', 'homologacao'),
    'timeout' => (int) env('FISCAL_TIMEOUT_HTTP', 30),
];
```

### 2.3 Variáveis de ambiente (.env)

```dotenv
FISCAL_API_BASE_URL=https://sua-instancia-fiscalapi
FISCAL_API_KEY=fk_test_sua-chave-de-homologacao
FISCAL_AMBIENTE=homologacao
FISCAL_TIMEOUT_HTTP=30
```

**Cuidado ambiente × chave:** a API key amarra o ambiente — chaves de produção
começam com `fk_live_` e chaves de homologação com `fk_test_`. O ambiente declarado
(`FISCAL_AMBIENTE`) viaja no header `X-Fiscal-Ambiente`; divergência entre ambiente e
chave resulta em **403** na API (→ `FiscalLib\Exceptions\AutenticacaoException`).
A lib não valida o prefixo localmente — a checagem acontece no servidor.

Detalhe do provider: apenas o valor literal `'producao'` vira `Ambiente::Producao`;
qualquer outro valor (incluindo vazio) vira `Ambiente::Homologacao`.

### 2.4 Formas de usar no ERP

Todas as três resoluem a mesma instância:

```php
use FiscalLib\FiscalLib;
use FiscalLib\Laravel\FiscalLibFacade;

// a) injeção por type-hint (alias do container)
public function emitir(FiscalLib $lib) { $lib->nfe()->... }

// b) helper do container
app('fiscal-lib')->nfe()->...;

// c) Facade
FiscalLibFacade::nfe()->emitir($documento);
FiscalLibFacade::lib(); // devolve a instância FiscalLib por baixo do Facade
```

O Facade (`FiscalLib\Laravel\FiscalLibFacade`) acessa o binding `'fiscal-lib'`;
`FiscalLibFacade::nfe()`, `::nfce()`, `::nfse()`, `::eventos()` funcionam por proxy
direto aos métodos da instância, e `::lib()` devolve a instância inteira.

---

## 3. Bootstrapping sem Laravel (PHP puro)

Para scripts, workers fora do Laravel ou testes:

```php
<?php

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\FiscalLib;

// Fábrica estática (rtrim da barra final da baseUrl):
$config = FiscalConfig::criar('https://sua-fiscalapi', 'fk_test_...', Ambiente::Homologacao);

$lib = FiscalLib::comFiscalApi($config);
```

Assinaturas reais:

```php
// FiscalConfig
public static function criar(string $baseUrl, string $apiKey, ?Ambiente $ambiente = null): self;

// Construtor completo (todos os defaults do código):
public function __construct(
    public readonly string $baseUrl,
    public readonly string $apiKey,
    public readonly ?Ambiente $ambiente = null,       // X-Fiscal-Ambiente opcional
    public readonly int $timeoutHttpSegundos = 30,
    public readonly int $tentativasRede = 3,          // 429/5xx/timeout com a MESMA idempotency key
    public readonly array $intervalosPolling = self::INTERVALOS_POLLING_PADRAO,  // [2, 5, 10, 20, 30, 60]
    public readonly int $timeoutTotalPollingSegundos = self::TIMEOUT_TOTAL_POLLING_PADRAO, // 300
    public readonly string $versaoLib = '0.1.0',      // vai no User-Agent
);

// FiscalLib
public static function comFiscalApi(FiscalConfig $config, ?\Psr\Http\Client\ClientInterface $http = null): self;
public function __construct(
    EmissorInterface $emissor,
    ?FiscalConfig $config = null,
    ?TaxEngineInterface $engine = null,
);
```

`comFiscalApi()` usa Guzzle por baixo (`new \GuzzleHttp\Client(['timeout' => $config->timeoutHttpSegundos])`).
Para trocar o transporte, injete um `Psr\Http\Client\ClientInterface` no segundo argumento.
Para plugar um emissor completamente diferente, implemente `FiscalLib\Contracts\EmissorInterface`
e use o construtor (`new FiscalLib(new MeuEmissor(), $config)`) — ver [payload-contract.md](payload-contract.md).

Serviços expostos por `FiscalLib`:

| Método | Retorna | Papel |
|---|---|---|
| `nfe()` | `Servicos\ServicoNfe` | NF-e (modelo 55): builder + emissão/consulta/PDF |
| `nfce()` | `Servicos\ServicoNfce` | NFC-e (modelo 65) |
| `nfse()` | `Servicos\ServicoNfse` | NFS-e Nacional (DPS) |
| `eventos()` | `Servicos\ServicoEventos` | cancelamento, CC-e, inutilização |
| `taxEngine()` | `Contracts\TaxEngineInterface` | cálculo tributário puro |
| `aguardarTerminal(string $documentoId)` | `Documento\ResultadoEmissao` | polling até estado terminal |
| `gestao()` | `?GestaoFiscalApi` | gestão FiscalAPI (certificados, api-keys, webhooks…); `null` se o emissor não for o adaptador FiscalAPI |
| `emissor()` | `EmissorInterface` | o emissor plugado |
| `statusNome(?StatusDocumento $status)` | `string` | status em texto ("AUTORIZADA" etc.), útil para logs |

---

## 4. Visão geral da arquitetura para o integrador

```
ERP (pedido)
 │
 ▼
TaxEngine (cálculo puro, stateless, sem I/O) ──► NfeTaxResultado (formato impostosV2)
 │
 ▼
NfeBuilder (validações R001–R016 relevantes + montagem) ──► NfeDocumento (modelo tipado)
 │
 ▼
EmissorInterface (porta)
 │
 ▼
EmissorFiscalApi (adaptador FiscalAPI)
 ├─ POST /v1/documentos-fiscais/nfe  → 202 Accepted + documentoId  (AceiteEmissao)
 ├─ GET  /v1/documentos-fiscais/{id} → consulta (polling até estado terminal)
 └─ GET  /v1/documentos-fiscais/{id}/pdf → DANFE (ArquivoPdf)
```

Pontos-chave:

- **A API não calcula tributos.** O `TaxEngine` da lib calcula e envia valores prontos;
  a FiscalAPI apenas valida a aritmética (`base × alíquota / 100`, arredondamento
  bancário, tolerância R$ 0,01). Se a aritmética não bater, a API rejeita com 422.
- **Emissão é assíncrona (202 + polling).** O POST devolve `AceiteEmissao` com o
  `documentoId` e status `PENDENTE`. O estado evolui via consulta; `AguardadorTerminal`
  (usado pelo fluxo alto nível) consulta até um estado terminal com backoff
  `2s → 5s → 10s → 20s → 30s → 60s` (teto) e timeout total de 300s (configurável).
- **Idempotency-Key.** Todo POST de emissão/evento leva `Idempotency-Key`. Se o ERP não
  fornecer, a lib gera um UUID v4 por chamada (`EmissorFiscalApi::novaIdempotencyKey()`).
  Retries de rede (429/5xx/timeout) são refeitos pela lib **com a mesma chave**
  (`tentativasRede`, default 3, backoff exponencial começando em 200 ms). O ERP deve
  fornecer uma chave **estável por tentativa lógica** quando retryar no nível dele — ver seção 9.
- **Erros HTTP seguem RFC 7807** (`application/problem+json`) e viram exceções tipadas
  de `FiscalLib\Exceptions\` — tabela na seção 7.5.

---

## 5. Calculando tributos com TaxEngine

O `TaxEngine` (implementação de `Contracts\TaxEngineInterface`) é puro e stateless:
recebe um `FiscalLib\Tax\Contextos\NfeTaxContext` (um item) e devolve um
`FiscalLib\Tax\Resultados\NfeTaxResultado`. Os resultados já saem no formato dos grupos
`impostosV2` do contrato (`icms`/`ipi`/`pis`/`cofins`/`ibsCbs`/`is`).

### 5.1 Contexto (NfeTaxContext)

Construção fluent via `NfeTaxContext::make()`. Métodos reais:

| Método | Assinatura | Para quê |
|---|---|---|
| `regime()` | `regime(RegimeTributario $regime): self` | CRT do emitente (1=SN, 2=SN excesso, 3=Normal) |
| `cfop()` | `cfop(string $cfop): self` | CFOP do item |
| `valores()` | `valores(string|int|float $quantidade, string|int|float $valorUnitario, string|int|float $desconto = 0): self` | calcula `valorBruto = qtd × unitário` (2 casas) e aplica desconto |
| `valorBruto()` | `valorBruto(string|int|float $valor): self` | define o bruto diretamente |
| `icms()` | `icms(string|int $origem, ?string $cst = null, ?string $csosn = null, string|int|float|null $aliquota = null, ?string $modBc = '3', string|int|float|null $reducaoBc = null, string|int|float|null $fcp = null): self` | ICMS próprio (CST regime normal OU CSOSN SN — nunca os dois) |
| `st()` | `st(string $modBcSt, string|int|float|null $mva = null, string|int|float|null $aliquotaSt = null, string|int|float|null $reducaoBcSt = null, string|int|float|null $fcpSt = null): self` | ST própria (CST 10/70/90, CSOSN 201/202/203/900) |
| `stRetida()` | `stRetida(string|int|float $baseCalculoStRetida, string|int|float $aliquotaStRetida, string|int|float|null $valorStRetido = null, string|int|float|null $valorIcmsSubstituto = null): self` | ST retida (CST 60 / CSOSN 500) |
| `diferimento()` | `diferimento(string|int|float $percentual): self` | CST 51 |
| `creditoSimples()` | `creditoSimples(string|int|float $percentual): self` | crédito SN (CSOSN 101/201/900) |
| `difalInterestadual()` | `difalInterestadual(int $aliquotaInterestadual, string|int|float $aliquotaInternaUfDestino, string|int|float|null $fcpUfDestino = null): self` | DIFAL (alíquota interestadual 4, 7 ou 12) |
| `ipi()` | `ipi(string $cst, string|int|float|null $aliquota = null, string $cEnq = '999'): self` | IPI |
| `pis()` / `cofins()` | `pis(string $cst, ?string $aliquota = null)` / idem | PIS/COFINS |
| `ibsCbs()` | `ibsCbs(IbsCbsEntrada $entrada): self` | reforma — LC 214/2025 |
| `is()` | `is(IsEntrada $entrada): self` | Imposto Seletivo |

Para IBS/CBS e IS use as entradas dedicadas:

```php
// IBS/CBS: alíquotas são do ERP; os valores o TaxEngine calcula
IbsCbsEntrada::criar(
    string $cstIbsCbs,          // 3 dígitos (SEPEC)
    string $cClassTrib,         // 6 dígitos — obrigatório
    $aliquotaIbsEstadual = null,
    $aliquotaIbsMunicipal = null,
    $aliquotaCbs = null,
);
// + comReducaoCbs($pct) / comReducaoIbsEstadual($pct) / comReducaoIbsMunicipal($pct)

// IS por valor ou por quantidade:
IsEntrada::porValor(string $cstIs, string $cClassTribIs, $aliquota);
IsEntrada::porQuantidade(string $cstIs, string $cClassTribIs, $aliquota, $baseCalculo, string $unidadeTributavel, $quantidadeTributavel);
```

### 5.2 Exemplo numérico completo (CST 10 + ST + IPI + PIS/COFINS)

Venda de 2 unidades a R$ 50,00 (bruto R$ 100,00), ICMS 18%, MVA 30%, ST 18%,
IPI 10%, PIS 1,65%, COFINS 7,60%:

```php
use FiscalLib\Tax\Contextos\NfeTaxContext;

$tributos = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()
        ->valores(quantidade: 2, valorUnitario: 50)
        ->cfop('5102')
        ->icms(0, cst: '10', aliquota: 18)
        ->st(modBcSt: '4', mva: 30, aliquotaSt: 18)
        ->ipi('50', aliquota: 10)
        ->pis('01', '1.65')
        ->cofins('01', '7.60')
);
```

Resultado (valores conferidos executando o código):

```
icms.baseCalculo = 100.00   icms.aliquota = 18.0000   icms.valor = 18.00
icms.st.baseCalculoSt = 130.00   icms.st.valorSt = 23.40   (BC_ST = 100 × 1,30; vICMSST = 130 × 18%)
ipi.valor = 10.00  (cEnq = '999')
pis.valor = 1.65   cofins.valor = 7.60
```

Helpers de leitura do resultado (usados na fórmula do total):

```php
$tributos->totalSt();    // '23.40' — vICMSST do item
$tributos->totalFcpSt(); // '0.00'  — vFCP-ST do item
$tributos->totalIpi();   // '10.00'
$tributos->totalIcms();  // '18.00'
$tributos->totalFcp();   // '0.00'  (FCP próprio não compõe o total)
$tributos->totalIs();    // '0.00'
$tributos->paraArray();  // array no formato impostosV2 (nulos omitidos)
```

### 5.3 DIFAL (interestadual para consumidor final)

Partilha vigente do Convênio 190/2017: 100% para o UF de destino (`valorIcmsOrigem = 0`).

```php
$difal = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()
        ->valores(1, 1000)
        ->cfop('6108')
        ->icms(0, cst: '00', aliquota: 12)
        ->difalInterestadual(7, 18, fcpUfDestino: 2)
);

$difal->icms->difal->aliquotaInterestadual; // 7
$difal->icms->difal->baseDestino;           // '1000.00' (vBCUFDest)
$difal->icms->difal->aliquotaDestino;       // '18.0000' (pICMSUFDest)
$difal->icms->difal->valorIcmsDestino;      // '180.00'  (vICMSUFDest)
$difal->icms->difal->valorIcmsOrigem;       // '0.00'    (vICMSUFRemet)
$difal->icms->difal->valorFcpDestino;       // '20.00'   (vFCPUFDest)
```

Sem `difalInterestadual()`, nada é calculado (o campo `difal` fica `null`).
Com ele, `aliquotaInterestadual` precisa ser 4, 7 ou 12 e a alíquota interna é
obrigatória — senão `ValidationException`.

### 5.4 Simples Nacional (CSOSN) e reforma (IBS/CBS, IS)

```php
// CSOSN 101 — crédito SN
$sn = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()
        ->regime(RegimeTributario::SimplesNacional)
        ->valores(2, 50)
        ->icms(0, csosn: '101')
        ->creditoSimples(2.5)
);
$sn->icms->csosn;                  // '101'
$sn->icms->valorCreditoSimples;    // '2.50' (sem ICMS próprio)

// IBS/CBS — valores "por fora" do total da nota
$reforma = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()
        ->valores(1, 100)
        ->icms(0, cst: '00', aliquota: 18)
        ->ibsCbs(IbsCbsEntrada::criar('000', '000001', aliquotaIbsEstadual: 9, aliquotaCbs: 1))
);
$reforma->ibsCbs->valorIbsEstadual; // '9.00'
$reforma->ibsCbs->valorCbs;         // '1.00'
$reforma->ibsCbs->totalIbs();       // '9.00' (estadual + municipal)
```

Prazos do IBS/CBS (per `fiscal-rules.md`): 03/08/2026 para Regime Normal e
04/01/2027 para Simples Nacional.

### 5.5 Do resultado para o ItemFiscal (formato impostosV2)

O `NfeTaxResultado` vai direto no 6º argumento de `Documento\ItemFiscal` (campo
`$tributos`) — o builder não remapeia nada, e o adaptador serializa via
`paraArray()` para o campo `impostosV2` do JSON. Assinatura real do construtor:

```php
public function __construct(
    public readonly string $codigo,
    public readonly string $descricao,
    public readonly string $quantidade,        // 4 decimais
    public readonly string $valorUnitario,     // até 10 decimais
    public readonly string $valorTotal,        // bruto (qtd × unitário), 2 decimais
    public readonly ?NfeTaxResultado $tributos = null,
    public readonly ?string $ncm = null,
    public readonly ?string $cest = null,
    public readonly ?string $cfop = null,
    public readonly ?string $gtin = null,      // default enviado: "SEM GTIN"
    public readonly ?string $unidade = null,   // default enviado: "UN"
    public readonly string $valorDesconto = '0.00',
);
```

Importante: `valorTotal` é o **bruto** (quantidade × valor unitário, sem desconto).
O builder valida `valorTotal == quantidade × valorUnitario` e falha se divergir.
O desconto condicionado/incondicionado do item entra em `valorDesconto` e a base do
TaxEngine já é `bruto − desconto`.

A tabela de CSTs/CSOSNs suportados, fórmulas por CST e regras de FCP/ST/diferimento
estão em [fiscal-rules.md](fiscal-rules.md) — não são duplicadas aqui. Resumo do que
**falha alto** no cálculo: seção 10 deste guia.

---

## 6. Montando a NF-e com NfeBuilder

### 6.1 Métodos do builder

Acesso: `$lib->nfe()->novo()` (que é `NfeBuilder::nfe()`) ou direto `NfeBuilder::nfe()`.
Todos os métodos são fluent (devolvem `$this`):

```php
public static function nfe(): self;              // instância nova
public static function make(): static;           // instância nova (respeita subclasses)

public function ambiente(Ambiente $ambiente): static;                          // default: Homologacao
public function serie(int $serie): static;                                     // default: 1 (validado 1–999)
public function naturezaOperacao(string $naturezaOperacao): static;            // obrigatória
public function finalidade(FinalidadeNfe $finalidade): static;                 // default: Normal
public function tipoOperacao(TipoOperacao $tipoOperacao): static;              // default: Saida
public function indicadorPresenca(IndicadorPresenca $indicadorPresenca): static;
public function consumidorFinal(IndicadorConsumidorFinal $consumidorFinal): static; // default: Sim
public function emitente(Emitente $emitente): static;
public function destinatario(Destinatario $destinatario): static;
public function addItem(ItemFiscal $item): static;
public function pagamento(FormaPagamento|string $forma, string|int|float $valor): static;
public function nfeReferenciada(string $chaveAcesso): static;                  // valida 44 posições + DV módulo 11
public function frete(string|int|float $valor): static;
public function seguro(string|int|float $valor): static;
public function outrasDespesas(string|int|float $valor): static;
public function descontoTotal(string|int|float $valor): static;                // desconto document-level
public function informacoesComplementares(string $texto): static;              // ver limitação na seção 10
public function build(): NfeDocumento;                                          // lança ValidationException
```

Enums reais usados acima (`FiscalLib\Common\Enums\`):

- `Ambiente`: `Producao = 'producao'`, `Homologacao = 'homologacao'` (string case).
- `FinalidadeNfe`: `Normal`, `Complementar`, `Ajuste`, `Devolucao` (string).
- `TipoOperacao`: `Entrada = 'entrada'`, `Saida = 'saida'`.
- `IndicadorPresenca`: `NaoSeAplica`, `Presencial`, `Internet`, `Teleatendimento`, `EntregaDomicilio`, `ForaEstabelecimento`, `Outros` (string; tem `codigo(): int` do layout 4.00).
- `IndicadorConsumidorFinal`: `Sim = 'sim'`, `Nao = 'nao'`.
- `FormaPagamento` (tPag SEFAZ, string): `Dinheiro = '01'`, `Cheque = '02'`, `CartaoCredito = '03'`, `CartaoDebito = '04'`, `CreditoLoja = '05'`, `ValeAlimentacao = '10'`, `ValeRefeicao = '11'`, `ValePresente = '12'`, `ValeCombustivel = '13'`, `DuplicataMercantil = '14'`, `Boleto = '15'`, `SemPagamento = '90'`, `Outros = '99'`.

### 6.2 Emitente e destinatário

```php
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Common\ValueObjects\Cpf;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\Emitente;

$emitente = new Emitente(
    documento: Cnpj::criar('12345678000199'),   // valida DV; aceita alfanumérico NT 009/2026
    razaoSocial: 'Empresa Emitente Ltda',
    nomeFantasia: 'Emitente',
    inscricaoEstadual: '123456789012',
    endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308', uf: 'SP'),
);

$destinatario = new Destinatario(
    documento: Cpf::criar('52998224725'),        // Cnpj|Cpf — valida DV
    nome: 'Cliente Teste',
    inscricaoEstadual: null,                     // 'ISENTO' p/ contribuinte com IE isenta
    endereco: new Endereco(
        cep: '01001000',
        logradouro: 'Praça da Sé',
        numero: '1',
        complemento: 'Sala 10',
        bairro: 'Sé',
        codigoMunicipioIbge: '3550308',          // 7 dígitos IBGE
        uf: 'SP',
        nomeMunicipio: 'São Paulo',
    ),
);
```

> **Nota sobre emitente na FiscalAPI:** o emitente da nota vem do **perfil do tenant**
> cadastrado na API (manage via `$lib->gestao()?->atualizarPerfil([...])`). Os dados de
> `Emitente` no builder servem para validação local e para outros emissores — o
> adaptador FiscalAPI **não envia** o emitente no payload.

`Cnpj::criar()`/`Cpf::criar()` lançam `InvalidValueException` em dígito verificador
inválido; há também `Cnpj::valido($valor): bool` / `Cpf::valido($valor): bool` que não
lançam.

### 6.3 Totais e fórmula do valor da nota

Você não calcula `valorNota` na mão: o `build()` chama `TotaisDocumento::calcular()`,
que aplica a fórmula determinística do contrato v2:

```
Fórmula v2 ATIVA quando houver: desconto (item ou documento), frete, seguro,
outras despesas ou IPI em item:
    valorNota = Σ brutos − descontos + frete + seguro + outras + Σ vICMSST + Σ vFCPST + Σ vIPI
Caso contrário:
    valorNota = Σ brutos
```

IBS/CBS e IS **não** entram no `valorNota` (são conferidos à parte pela API).
Detalhes em [fiscal-rules.md](fiscal-rules.md).

### 6.4 Validações pré-emissão que disparam exceção

`build()` acumula erros por campo e lança **uma única** `ValidationException` com
`erros(): array<string, list<string>>` quando algo falha. Regras realmente implementadas
em `NfeBuilder::build()`:

| Validação | Mensagem base |
|---|---|
| Série entre 1 e 999 | "Série deve estar entre 1 e 999." |
| Natureza da operação obrigatória | "Natureza da operação é obrigatória." |
| Ao menos um item | "A nota deve ter ao menos um item." |
| `valorTotal = quantidade × valorUnitario` por item | "Quantidade × valorUnitario (X) difere do valorTotal (Y)." |
| Coerência CFOP × tipo de operação (R001/R002) | "CFOP X é de saída, mas tipoOperacao = entrada (R001/R002)." (e inverso) |
| Devolução exige NF-e referenciada (v2 F4) | "Devolução exige ao menos uma NF-e referenciada (v2 F4)." |
| Pagamento não negativo | "Valor de pagamento negativo." |

O docblock do builder registra ainda que R003/R004 (DIFAL) são aplicados pelo
`TaxEngine` e que R012 é invertido (a numeração é controlada pelo emissor/API, não pelo ERP).
Validações específicas de NFC-e (pagamento obrigatório, vNF ≤ 10.000 sem destinatário,
sem IPI, consumidor final obrigatório) ficam no `NfceBuilder` (`$lib->nfce()->novo()`).

Erros de tributação (CST incompatível, campo ausente para o CST, aritmética) acontecem
**antes**, no `calcularNfe()` — ver seção 5 e tabela da seção 10.

### 6.5 Exemplo completo end-to-end: pedido do ERP → documento → emissão

Cenário: venda com 2 × R$ 50,00 + ST + IPI (os mesmos tributos da seção 5.2, resultado
`valorNota = 133.40` — conferido executando o código).

```php
<?php

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\IndicadorPresenca;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\FiscalLib;
use FiscalLib\Tax\Contextos\NfeTaxContext;

// $lib = resolvido do container (app(FiscalLib::class) / FiscalLibFacade::lib())
// $pedido = modelo do ERP com itens do catálogo já parametrizados (NCM/CFOP/CST/MVA...)

// 1) Tributos por item (uma chamada por item; aqui só há um)
$tributos = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()
        ->valores(quantidade: 2, valorUnitario: 50)
        ->cfop('5102')
        ->icms(0, cst: '10', aliquota: 18)
        ->st(modBcSt: '4', mva: 30, aliquotaSt: 18)
        ->ipi('50', aliquota: 10)
        ->pis('01', '1.65')
        ->cofins('01', '7.60')
);

// 2) Documento
$documento = $lib->nfe()->novo()
    ->ambiente(Ambiente::Homologacao)
    ->serie(1)
    ->naturezaOperacao('Venda de mercadoria')
    ->indicadorPresenca(IndicadorPresenca::Internet)
    ->destinatario(new Destinatario(
        Cnpj::criar('11444777000161'),
        'Cliente Teste Ltda',
        endereco: new Endereco(
            cep: '01001000',
            logradouro: 'Praça da Sé',
            numero: '1',
            bairro: 'Sé',
            codigoMunicipioIbge: '3550308',
            uf: 'SP',
        ),
    ))
    ->addItem(new ItemFiscal(
        codigo: 'SKU1',
        descricao: 'Produto de teste',
        quantidade: '2.0000',
        valorUnitario: '50.00',
        valorTotal: '100.00',          // 2 × 50.00 — o builder confere
        tributos: $tributos,
        ncm: '12345678',
        cfop: '5102',
    ))
    ->pagamento(FormaPagamento::CartaoCredito, 133.40)
    ->build();

// 3) Emissão (alto nível — bloqueia até estado terminal; ver seção 7)
$resultado = $lib->nfe()->emitir($documento);

if ($resultado->isAutorizada()) {
    // persistir: $resultado->chaveAcesso, protocoloAutorizacao, numero, serie, xmlAssinado...
}
```

### 6.6 Devolução com NF-e referenciada

`finalidade(Devolucao)` exige ao menos uma `nfeReferenciada()` (a chave passa pela
validação de 44 posições + DV módulo 11 — chave de exemplo abaixo é válida):

```php
use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\FinalidadeNfe;
use FiscalLib\Common\Enums\TipoOperacao;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\Nfe\NfeBuilder;

$devolucao = NfeBuilder::nfe()
    ->ambiente(Ambiente::Homologacao)
    ->serie(1)
    ->naturezaOperacao('Devolucao de venda')
    ->finalidade(FinalidadeNfe::Devolucao)
    ->tipoOperacao(TipoOperacao::Entrada)          // CFOPs 1xxx/2xxx/3xxx (R002)
    ->destinatario(new Destinatario(Cnpj::criar('11444777000161'), 'Cliente Teste Ltda'))
    ->nfeReferenciada('42260912345678000199550010000004211000000425')
    ->addItem(new ItemFiscal(
        codigo: 'SKU1',
        descricao: 'Produto de teste',
        quantidade: '1.0000',
        valorUnitario: '100.00',
        valorTotal: '100.00',
        ncm: '12345678',
        cfop: '1202',                              // CFOP de entrada — coerente com tipoOperacao
    ))
    ->build();
```

---

## 7. Emitindo

### 7.1 Alto nível: `emitir()` (aguarda o estado terminal)

```php
public function emitir(NfeDocumento|NfseDocumento $documento, ?OpcoesEmissao $opcoes = null): ResultadoEmissao;
```

`ServicoNfe::emitir()` (herdado de `ServicoDocumentos`) submete o documento e faz
polling via `AguardadorTerminal` até um estado terminal. **Rejeições da SEFAZ NÃO
lançam exceção aqui** — o resultado traz `status`/`motivoStatus` (se preferir exceção,
chame `ResultadoEmissao::exigirAutorizada()`). Estados transitórios
(`CONTINGENCIA`, `CANCELAMENTO_PENDENTE`) são tratados como não-terminais: a API faz o
retry sozinha e o polling continua.

Atenção: este método **bloqueia** (dorme entre consultas, até 300s com os intervalos
padrão). Em request HTTP do ERP, prefira o fluxo de baixo nível + fila (seção 9).

### 7.2 Baixo nível: `emitirAsync()` (202 + id)

```php
public function emitirAsync(NfeDocumento|NfseDocumento $documento, ?OpcoesEmissao $opcoes = null): AceiteEmissao;
```

```php
use FiscalLib\Contracts\OpcoesEmissao;

// A chave deve ser ESTÁVEL por tentativa lógica (gere uma vez, guarde no pedido):
$opcoes = OpcoesEmissao::comIdempotencia($pedido->fiscal_idempotency_key);
// equivalente: new OpcoesEmissao($pedido->fiscal_idempotency_key);

$aceite = $lib->nfe()->emitirAsync($documento, $opcoes);

// Persista IMEDIATAMENTE (o contrato do adaptador recomenda "antes de qualquer coisa"):
//   $aceite->documentoId  (string) — identificador p/ consulta/eventos/PDF
//   $aceite->status       (string) — normalmente 'PENDENTE'
//   $aceite->ambiente     (?string), $aceite->criadoEm (?string),
//   $aceite->linkConsulta (?string), $aceite->raw (?array)
$pedido->update(['fiscal_documento_id' => $aceite->documentoId]);
```

Depois, de qualquer lugar (job, comando, controller):

```php
$resultado = $lib->aguardarTerminal($aceite->documentoId);   // FiscalLib::aguardarTerminal(string): ResultadoEmissao
// ou uma única consulta, sem dormir:
$resultado = $lib->nfe()->consultar($aceite->documentoId);   // ServicoDocumentos::consultar(string): ResultadoEmissao
```

`AguardadorTerminal` usa os intervalos/timeout da `FiscalConfig`; se o timeout total
(300s por padrão) acabar sem estado terminal, lança `ApiIndisponivelException` com a
mensagem "Timeout de {N}s aguardando estado terminal... Continue consultando depois."
Ou seja: nesse caso o documento continua em processamento — apenas volte a consultar.

### 7.3 `ResultadoEmissao` e `StatusDocumento`

Campos reais de `Documento\ResultadoEmissao`:

```php
public readonly string  $documentoId;
public readonly string  $status;                    // texto canônico ('AUTORIZADA', ...)
public readonly ?StatusDocumento $statusDocumento;  // enum (null se status desconhecido)
public readonly ?string $tipo;                      // 'NFE', ...
public readonly ?string $ambiente;                  // 'homologacao' | 'producao'
public readonly ?int    $serie;
public readonly ?int    $numero;
public readonly ?string $chaveAcesso;
public readonly ?string $protocoloAutorizacao;
public readonly ?string $xmlAssinado;
public readonly ?string $xmlRetornoSefaz;
public readonly ?string $motivoStatus;
public readonly ?string $criadoEm;
public readonly ?string $atualizadoEm;
public readonly ?array  $raw;                       // payload original do emissor
```

Métodos úteis: `isTerminal()`, `isAutorizada()`, `isRejeicaoSefaz()`,
`statusEnum(): ?StatusDocumento`, `exigirAutorizada(): self` (lança
`RejeicaoSefazException` quando não autorizada) e `extrairCodigoRejeicao(): ?string`
(procura `Rejeição/Denegação \d{3}` no motivo e `<cStat>\d{3}</cStat>` no XML).

`StatusDocumento` (enum string, valores literais do contrato) e comportamento:

| Status | Terminal? |
|---|---|
| `AUTORIZADA`, `REJEITADA`, `DENEGADA`, `CANCELADA`, `ERRO_INTERNO` | sim (`isTerminal() = true`) |
| `PENDENTE`, `PROCESSANDO`, `CONTINGENCIA`, `CANCELAMENTO_PENDENTE` | não (`isTransitorio()`) |
| `ERRO_CANCELAMENTO` | não (transitório) |

Para logs: `FiscalLib::statusNome($resultado->statusDocumento)` devolve o texto do
status (ou `'DESCONHECIDO'`).

### 7.4 Baixar o PDF (DANFE)

Existe no adaptador e é exposto pelo serviço:

```php
public function baixarPdf(string $documentoId, bool $emBase64 = false): ArquivoPdf;
```

```php
// binário (default):
$pdf = $lib->nfe()->baixarPdf($resultado->documentoId);       // GET /v1/documentos-fiscais/{id}/pdf
file_put_contents("danfe-{$resultado->numero}.pdf", $pdf->bytes());  // bytes() decodifica se veio em base64

// base64:
$pdf64 = $lib->nfe()->baixarPdf($resultado->documentoId, true); // GET .../pdf?formato=base64
```

`Documento\ArquivoPdf` tem `conteudo`, `isBase64`, `contentType` e `bytes(): string`.

### 7.5 Tratamento de erros: tabela status HTTP → exceção

Do `ClienteHttp` do adaptador (`FiscalLib\Adapters\FiscalApi\ClienteHttp`):

| Status HTTP | Exceção (`FiscalLib\Exceptions\`) | Quando |
|---|---|---|
| 400 / 422 | `ValidacaoApiException` | payload inválido / inconsistência semântica-aritmética |
| 401 / 403 | `AutenticacaoException` | API key ausente/inválida/revogada ou ambiente divergente da chave |
| 404 | `NaoEncontradoException` | documento/recurso inexistente ou de outro tenant |
| 409 | `EstadoInvalidoException` | estado incompatível (ex.: cancelar não-AUTORIZADA) |
| 429 | `LimiteRequisicoesException` | rate limit |
| 5xx | `ApiIndisponivelException` | indisponibilidade (a lib retria com a mesma Idempotency-Key) |
| timeout/rede | `ApiIndisponivelException` | idem |

Todas as exceções de HTTP estendem `ApiHttpException` (`FiscalLibException` →
`\RuntimeException`), que carrega `status: ?int`, `problem: ?array` (ProblemDetails
RFC 7807 completo), `campo: ?string` e `errosPorCampo: array<string, list<string>>`.
Erros da SEFAZ (documento `REJEITADA`/`DENEGADA`) **não** são exceções HTTP — chegam
via `ResultadoEmissao`; `exigirAutorizada()` lança `RejeicaoSefazException`:

```php
try {
    $resultado->exigirAutorizada();
} catch (RejeicaoSefazException $e) {
    $e->status;           // 'REJEITADA'
    $e->codigoRejeicao;   // ex.: '539' (extraído do motivoStatus ou do XML de retorno)
    $e->motivoStatus;     // 'Rejeição 539: Duplicidade de NF-e' (ex.)
    $e->xmlRetornoSefaz;  // XML completo da SEFAZ, quando disponível
    $e->chaveAcesso;      // quando disponível
}
```

`ValidacaoApiException`/`ValidationException` expõem `errosPorCampo` /
`erros(): array<string, list<string>>` para exibir mensagens por campo no ERP.

Validações locais da lib (antes de qualquer HTTP) lançam `ValidationException`
(build), `MissingFieldException` (campo obrigatório ausente no cálculo),
`TaxInconsistencyException` (combinação tributária fora do contrato) e
`InvalidValueException` (CNPJ/CPF/chave/CFOP inválidos).

---

## 8. Pós-emissão

### 8.1 Eventos (`FiscalLib\Servicos\ServicoEventos` via `$lib->eventos()`)

```php
// Cancelar (documento AUTORIZADA). Justificativa 15–1000 chars (validada na lib).
$resultadoEvento = $lib->eventos()->cancelar(
    $documentoId,
    'Pedido cancelado pelo cliente antes do faturamento', // ≥ 15 chars
    // ?OpcoesEvento — idempotência opcional: new OpcoesEvento($chave)
);

// Carta de correção — APENAS NF-e (modelo 55). NFC-e: cancele e reemita. 15–1000 chars.
$cc = $lib->eventos()->cartaCorrecao($documentoId, 'Corrige o frete do item 1');

// Inutilizar faixa de numeração (não ligada a documento).
use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\ModeloDocumento;
use FiscalLib\Documento\InutilizacaoPedido;

$inutilizacao = $lib->eventos()->inutilizar(new InutilizacaoPedido(
    ambiente: Ambiente::Homologacao,
    modelo: ModeloDocumento::Nfe,          // Nfe = 55, Nfce = 65 (enum int)
    serie: 1,
    numeroInicial: 10,
    numeroFinal: 12,
    justificativa: 'Numeracao pulada por falha no sistema', // 15–1000 chars
));

// Consultar estado de uma inutilização:
$lib->eventos()->consultarInutilizacao($inutilizacao->eventoId);
```

Assinaturas reais (mesmas do `EmissorInterface`):

```php
public function cancelar(string $documentoId, string $justificativa, ?OpcoesEvento $opcoes = null): ResultadoEvento;
public function cartaCorrecao(string $documentoId, string $correcao, ?OpcoesEvento $opcoes = null): ResultadoEvento;
public function inutilizar(InutilizacaoPedido $pedido, ?OpcoesEvento $opcoes = null): ResultadoEvento;
public function consultarInutilizacao(string $eventoId): ResultadoEvento;
```

`Documento\ResultadoEvento` carrega: `eventoId`, `tipo` (`CANCELAMENTO`, `CCE`,
`INUTILIZACAO` — enum `TipoEvento`), `status`, `?documentoId`, `?criadoEm`,
`?motivoStatus`, `?raw`.

Cancelamento vira estado `CANCELAMENTO_PENDENTE` → `CANCELADA` (por isso
`CANCELAMENTO_PENDENTE` é transitório no polling).

`numeroFinal < numeroInicial` na inutilização lança `ValidationException` localmente.
Textos fora de 15–1000 chars lançam `ValidationException` na lib (regra SEFAZ).

### 8.2 Consulta e PDF

```php
$resultado = $lib->nfe()->consultar($documentoId);  // consulta única
$pdf = $lib->nfe()->baixarPdf($documentoId);        // DANFE (seção 7.4)
```

### 8.3 Webhooks com validação HMAC

Configure a URL e o segredo no tenant da FiscalAPI:

```php
$lib->gestao()?->configurarWebhooks(
    'https://erp.suaempresa.com.br/webhooks/fiscal',
    $webhookSecret,   // segredo compartilhado; guarde com segurança
);
// GET disponível: $lib->gestao()?->webhooks()
```

A FiscalAPI assina cada entrega com HMAC-SHA256 sobre `"{timestamp}.{corpo}"` no header
`X-Fiscal-Signature` (formato `t=<unix_ts>,v1=<hex>`; a lib também aceita a variante
`sha256=<hex>`), com janela anti-replay de 5 minutos. Valide com
`FiscalLib\Webhook\VerificadorAssinaturaHmac` **sobre o corpo bruto**:

```php
<?php

use FiscalLib\Exceptions\FiscalLibException;
use FiscalLib\Webhook\VerificadorAssinaturaHmac;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/fiscal', function (Request $request) {
    $secret = (string) env('FISCAL_WEBHOOK_SECRET');

    try {
        VerificadorAssinaturaHmac::validar(
            $request->getContent(),                     // corpo BRUTO, antes de qualquer parse
            $request->header('X-Fiscal-Signature'),
            $secret,
        );
    } catch (FiscalLibException $e) {
        // 'Webhook sem assinatura (X-Fiscal-Signature).'
        // 'Assinatura de webhook malformada.'
        // 'Assinatura de webhook fora da janela anti-replay.'  (|agora − t| > 300s)
        // 'Assinatura de webhook inválida.'
        return response('assinatura invalida', 400);
    }

    $payload = $request->json()->all();
    // Localize o documento por $payload['id'] (ou o formato definido pela FiscalAPI)
    // e dispare a consulta canônica: $lib->nfe()->consultar($documentoId).

    return response('ok', 200);
})->withoutMiddleware(VerifyCsrfToken::class);
```

> **Limitação conhecida:** a lib fornece a **verificação** da assinatura, mas não
> define DTOs para o payload da entrega de webhook — trate-o como array JSON e confirme
> o formato no ambiente da FiscalAPI. Para obter o estado confiável, sempre reconsulte o
> documento (`$lib->nfe()->consultar($documentoId)`) em vez de confiar no payload.

Para testar a validação localmente, o próprio verificador monta headers de teste:

```php
VerificadorAssinaturaHmac::header($corpo, $segredo);        // "t=<agora>,v1=<hex>"
VerificadorAssinaturaHmac::calcular($corpo, $timestamp, $segredo); // hex esperado
```

### 8.4 Gestão (só existe no adaptador FiscalAPI)

`$lib->gestao()` devolve `?GestaoFiscalApi` (`null` com emissor genérico). Métodos reais:

```php
$lib->gestao()?->enviarCertificado('/caminho/certificado.pfx', $senha); // multipart, até 10 MB
$lib->gestao()?->listarCertificados();       // monitore o campo validoAte
$lib->gestao()?->criarApiKey(Ambiente::Homologacao, 'ERP produção');
$lib->gestao()?->listarApiKeys();
$lib->gestao()?->revogarApiKey($id);
$lib->gestao()?->perfil();
$lib->gestao()?->atualizarPerfil([...]);     // ex.: IE, endereço, CSC da NFC-e (gravado cifrado)
$lib->gestao()?->statusServico(ModeloDocumento::Nfe, Ambiente::Homologacao);
$lib->gestao()?->notasRecebidas($nsu);       // Distribuição DFe
$lib->gestao()?->manifestar($notaRecebidaId, TipoManifestacao::CienciaOperacao);
```

Métodos de gestão devolvem `array` (JSON decodificado), sem DTO rígido.

---

## 9. Recomendações de produção para o ERP

### 9.1 Persistência: documentoId e Idempotency-Key no pedido

Colunas sugeridas na tabela de faturamento (nomes livres — o que importa é a semântica):

| Coluna | Tipo | Uso |
|---|---|---|
| `fiscal_idempotency_key` | `uuid unique` | gerada **uma vez**, quando o pedido entra no faturamento |
| `fiscal_documento_id` | `string nullable unique` | `AceiteEmissao->documentoId` do 202 |
| `fiscal_status` | `string nullable` | último `status` conhecido (espelho; fonte de verdade = consulta) |
| `fiscal_chave_acesso` | `string nullable` | `ResultadoEmissao->chaveAcesso` |
| `fiscal_numero` / `fiscal_serie` | `int nullable` | `ResultadoEmissao->numero/serie` |

### 9.2 Retry: sempre com a mesma Idempotency-Key

- A lib já retria **internamente** 429/5xx/timeout com a mesma chave
  (`FiscalConfig->tentativasRede`, default 3; backoff 200 ms × 2^n).
- Retry no nível do ERP (job falhou, processo morreu): **reenvie o mesmo documento com a
  mesma `OpcoesEmissao::comIdempotencia($key)`**. Trocar a chave pode criar documentos
  duplicados e consumir números (o número é reservado pelo emissor — R012 invertido).
- Se você chamar o alto nível `emitir()` **sem** chave, cada chamada gera um UUID novo e
  o servidor não consegue deduplicar — por isso a chave persistida é obrigatória em produção.

### 9.3 Polling em fila (job), não no request HTTP

`emitir()` (alto nível) dorme entre consultas — adequado para scripts/CLI, não para
worker de request. Padrão recomendado no ERP:

```php
<?php

namespace App\Jobs;

use FiscalLib\FiscalLib;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ConsultarDocumentoFiscal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 15;       // ~15 × 30s = ~7,5 min de cobertura

    public function __construct(private readonly string $documentoId)
    {
    }

    public function handle(FiscalLib $lib): void   // injeção via alias 'fiscal-lib'
    {
        $resultado = $lib->nfe()->consultar($this->documentoId);

        // Persistir espelho do status no pedido...
        // Event::dispatch(new DocumentoFiscalAtualizado($resultado));

        if (! $resultado->isTerminal()) {
            $this->release(30);   // volta a fila; CONTINGENCIA/CANCELAMENTO_PENDENTE também passam por aqui
        }
    }
}
```

Fluxo: dispatch `ConsultarDocumentoFiscal` logo após o `emitirAsync()`. Estados
transitórios (`PENDENTE`, `PROCESSANDO`, `CONTINGENCIA`, `CANCELAMENTO_PENDENTE`) →
`release()`. Terminais → persista e notifique. `AguardadorTerminal` lançar
`ApiIndisponivelException` por timeout não significa erro do documento — apenas
reagende a consulta (o job acima já faz isso naturalmente, consultando de novo).

### 9.4 Logs

- Logue `ResultadoEmissao->raw` (payload completo do emissor) em falhas — é a matéria-prima para suportar.
- Use `FiscalLib::statusNome()` para status legível em linha de log.
- `RejeicaoSefazException` já traz `codigoRejeicao` extraído — ideal para indexar por código.
- Erros HTTP expõem `problem` (RFC 7807) — inclua no log com contexto do pedido.

### 9.5 Homologação vs produção

- Homologação: `FISCAL_AMBIENTE=homologacao` + chave `fk_test_...`.
- Produção: `FISCAL_AMBIENTE=producao` + chave `fk_live_...` (a divergência dá 403).
- Antes de abrir produção: `$lib->gestao()?->statusServico(ModeloDocumento::Nfe, Ambiente::Producao)`
  para checar disponibilidade, e confira o perfil do tenant
  (`$lib->gestao()?->perfil()`) — emitente/IE/endereço vêm de lá.
- O ambiente vai no payload **e** no header `X-Fiscal-Ambiente`; o mesmo processo
  Emitente→builder→emissor funciona nos dois ambientes, só muda a config.

---

## 10. Erros e limitações conhecidas

Confirmados no código (`Tax\TaxEngine`, builders, adaptador) e em [fiscal-rules.md](fiscal-rules.md):

| # | Limitação | Comportamento |
|---|---|---|
| 1 | ICMS CST `02/15/30/53/61`, `ICMSPart` e `ICMSST` fora do contrato | `TaxInconsistencyException` no `calcularNfe()` — a API rejeitaria com 422 |
| 2 | **CST 50 também não está no conjunto suportado do código** (`00/10/20/40/41/51/60/70/90`) | `TaxInconsistencyException`. Nota: a tabela de ICMS de [fiscal-rules.md](fiscal-rules.md) menciona "40/41/50" sem valores, mas o código não aceita CST 50 — prevalece o código |
| 3 | PIS/COFINS CST `03` (por quantidade) fora do contrato atual | `TaxInconsistencyException` |
| 4 | PIS/COFINS CST fora de `01/02/04–09/99` | `TaxInconsistencyException` |
| 5 | IPI CST fora de `00, 01–05, 49, 50, 51, 99` | `TaxInconsistencyException` |
| 6 | CST `00` com redução de BC | `TaxInconsistencyException` ("use CST 20") |
| 7 | CST `20/70` sem `percentualReducaoBc`; CST tributado sem `aliquotaIcms`; ST própria sem `modBcSt + aliquotaSt`; CST `60`/CSOSN `500` sem `baseCalculoStRetida + aliquotaStRetida` | `MissingFieldException` |
| 8 | DIFAL sem `aliquotaInterestadual = 4/7/12` ou sem alíquota interna | `ValidationException` |
| 9 | `informacoesComplementares` do `NfeDocumento` | **ignorado pela FiscalAPI** (não vai no payload do adaptador; comentário no próprio campo) — útil apenas para outros emissores |
| 10 | `Emitente` no builder | não vai no payload da FiscalAPI (emitente vem do perfil do tenant) — serve para validação local/outros emissores |
| 11 | Consulta de documento | apenas por `documentoId` (do aceite); não há consulta por chave de acesso na `EmissorInterface` |
| 12 | Webhooks | só verificação HMAC; sem DTO/parsing do payload da entrega (seção 8.3) |
| 13 | CNPJ alfanumérico (NT 009/2026) | aceito, mas valida apenas charset `[0-9A-Z]{14}` — algoritmo de DV ainda não implementado (comentário no `Cnpj`) |
| 14 | Núcleo não faz (responsabilidade do emissor, conforme [payload-contract.md](payload-contract.md) §4) | assinatura XML, transmissão SEFAZ direta, certificado, contingência local, numeração, guarda de XML |
| 15 | IBS/CBS e IS | calculados pela lib, mas **não** entram no `valorNota` (fórmula v2 do contrato) |
| 16 | Grupos de NF-e ausentes no modelo `NfeDocumento` | transporte/volumes, fatura/duplicatas, compra/importação, exportação, produtos específicos (combustível, medicamento, etc.) não têm campos — não é possível enviá-los pelo modelo atual |
| 17 | CC-e (carta de correção) | apenas NF-e (modelo 55); NFC-e: cancelar e reemitir |
| 18 | Valores monetários/decimais | sempre `string` com casas fixas no modelo (ex.: `'100.00'`, `'18.0000'`); o adaptador converte para número JSON no envio |

Nenhuma destas limitações é silenciosa: todas as combinações não suportadas de CST/CSOSN
falham alto com `TaxInconsistencyException`/`MissingFieldException`/`ValidationException`
antes de sair da aplicação.
