# Spec Técnica — Biblioteca Fiscal: NF-e · NFC-e · NFS-e Nacional

**Versão:** 1.0  
**Base normativa:** NT 2025.002-RTC v1.40 (NF-e/NFC-e) · NT 004 v2.00 / 007/2026 / 009/2026 (NFS-e Nacional) · LC 214/2025 · Ajuste SINIEF 12/2026  
**Stacks:** PHP 8.1+ (Composer) e C# .NET 8+ (NuGet)  
**Repositório:** monorepo `fiscal-lib/` com pacotes independentes por stack  
**Licença:** MIT  

---

## 1. Visão Geral e Responsabilidades

### 1.1 O que a lib faz

A biblioteca é responsável por:

1. **Montar o payload** — construir o objeto de dados (`DTO`) validado que representa a nota antes de qualquer XML ou transmissão.
2. **Calcular tributos** — resolver ISS, PIS, COFINS, ICMS, IPI, IBS, CBS e IS conforme regime tributário do emitente, CST do produto/serviço, CFOP e operação.
3. **Validar regras de negócio** — consistência entre campos antes de enviar à API .NET. Não valida XSD (isso é da API).
4. **Serializar o contrato HTTP** — produzir o JSON que a API .NET Unimake consome.
5. **Deserializar a resposta** — mapear o retorno da API para um `NfResponse` tipado.

### 1.2 O que a lib NÃO faz

- Assinar XML digitalmente (responsabilidade da API .NET).
- Transmitir para a SEFAZ (responsabilidade da API .NET).
- Gerar PDF/DANFSe (responsabilidade da API .NET).
- Persistir dados (responsabilidade do ERP/Laravel).
- Consultar tabelas IBGE, NBS ou CEST online — esses dados devem ser fornecidos pelo ERP via configuração.

### 1.3 Contratos fixos com a API .NET

> **⚠️ ATUALIZAÇÃO (2026-09-07):** o contrato abaixo **não é o da FiscalAPI real**.
> A API implementa `POST /v1/documentos-fiscais/nfe|nfce|nfse/dps` (fluxo
> **assíncrono**: `202 {id, status: PENDENTE}` + polling em `GET /v1/documentos-fiscais/{id}`),
> autenticação `Authorization: ApiKey fk_test_/fk_live_` + header `Idempotency-Key`
> obrigatório, `ambiente` como string (`"producao"/"homologacao"`) e DTOs
> `EmissaoRequest`/`impostosV2` em camelCase com erros RFC 7807. A lib foi
> implementada contra o contrato real — ver `plano-implementacao.md` (§5, desvios)
> e `docs/payload-contract.md`.

A API .NET expõe:

```
POST /fiscal/nfe/emitir
POST /fiscal/nfce/emitir
POST /fiscal/nfse/emitir
POST /fiscal/nfe/cancelar
POST /fiscal/nfse/cancelar
POST /fiscal/nfe/substituir
POST /fiscal/nfse/substituir
GET  /fiscal/nfe/{chave}/status
GET  /fiscal/nfse/{chave}/status
GET  /fiscal/nfse/{chave}/pdf
```

Corpo de entrada: JSON produzido pela lib (ver seção 8).  
Corpo de saída: `NfResponse` mapeado pela lib (ver seção 9).

---

## 2. Estrutura de Pacotes e Módulos

```
fiscal-lib/
├── php/
│   ├── src/
│   │   ├── Common/          # DTOs, enums, value objects compartilhados
│   │   ├── Tax/             # Motor tributário (puro, sem I/O)
│   │   ├── Nfe/             # Builder + Validator NF-e (mod 55)
│   │   ├── Nfce/            # Builder + Validator NFC-e (mod 65)
│   │   ├── Nfse/            # Builder + Validator NFS-e Nacional
│   │   ├── Http/            # Serialização/deserialização do contrato HTTP
│   │   └── Contracts/       # Interfaces (PSR-compliant)
│   └── tests/
├── dotnet/
│   ├── FiscalLib/
│   │   ├── Common/
│   │   ├── Tax/
│   │   ├── Nfe/
│   │   ├── Nfce/
│   │   ├── Nfse/
│   │   ├── Http/
│   │   └── Contracts/
│   └── FiscalLib.Tests/
└── docs/
    ├── fiscal-rules.md
    ├── payload-contract.md
    └── changelog.md
```

---

## 3. Value Objects e Enums Compartilhados (`Common/`)

### 3.1 Enums obrigatórios

```
RegimeTributario
  SimplesNacional   = 1
  SimplesNacionalExcessoReceita = 2
  RegimeNormal      = 3   # cobre Lucro Presumido e Lucro Real

AmbienteSEFAZ
  Producao          = 1
  Homologacao       = 2

ModeloDocumento
  Nfe               = 55
  Nfce              = 65

TipoOperacao
  Entrada           = 0
  Saida             = 1

FinalidadeNfe
  Normal            = 1
  Complementar      = 2
  Ajuste            = 3
  Devolucao         = 4

IndicadorPresenca   # NFe
  NaoSeAplica       = 0
  OperacaoPresencial = 1
  OperacaoInternet  = 2
  OperacaoTeleatendimento = 3
  NfceEntregaDomicilio = 4
  OperacaoPresencialForaEstabelecimento = 5
  Outros            = 9

TipoConsumidorFinal
  Normal            = 0
  ConsumidorFinal   = 1

IndicadorIEDestinatario
  ContribuinteICMS  = 1
  IsentoICMS        = 2
  NaoContribuinte   = 9

# NFS-e específico
RegimeEspecialTributacao
  Nenhum            = 0
  AtoCooperado      = 1
  Estimativa        = 2
  MicroempresaMunicipal = 3
  NotarioOuRegistrador = 4
  MeEppOptanteSimplesNacional = 5
  Mei               = 6

ExigibilidadeISS
  Exigivel          = 1
  NaoIncidencia     = 2
  Isencao           = 3
  Exportacao        = 4
  ImunidadeFiscal   = 5
  ExigibilidadeSuspensaPorDecisaoJudicial = 6
  ExigibilidadeSuspensaPorProcedAdm = 7

TipoRetencaoPisCofins   # NT 007/2026 — campo tpRetPisCofins expandido
  SemRetencao           = 0
  RetemPis              = 1
  RetemCofins           = 2
  RetemPisECofins       = 3
  RetemPisCofinsECsll   = 4
  RetemCsll             = 5
  RetemPisECsll         = 6
  RetemCofinsECsll      = 7
```

### 3.2 Value Objects

```
Cnpj            # valida 14 dígitos alfanuméricos (NT 009/2026: CNPJ alfanumérico a partir jul/2026)
Cpf             # valida 11 dígitos
CepBr           # 8 dígitos
CodigoMunibge   # 7 dígitos (cMun IBGE)
ChaveAcesso     # 44 dígitos (NF-e/NFC-e) ou 50 posições (NFS-e Nacional)
CodigoNbs       # 9 dígitos — Nomenclatura Brasileira de Serviços
CodigoCfop      # 4 dígitos
Aliquota        # decimal 0–100 com 4 casas
ValorMonetario  # decimal com 2 casas, não negativo
```

---

## 4. Motor Tributário (`Tax/TaxEngine`)

O `TaxEngine` é o núcleo calculador. Ele recebe um contexto de operação e devolve todos os tributos calculados. É stateless e puro (sem I/O, sem banco).

### 4.1 Interface

```php
// PHP
interface TaxEngineInterface
{
    public function calcularNfe(NfeTaxContext $ctx): NfeTaxResult;
    public function calcularNfce(NfceTaxContext $ctx): NfceTaxResult;
    public function calcularNfse(NfseTaxContext $ctx): NfseTaxResult;
}
```

```csharp
// C#
public interface ITaxEngine
{
    NfeTaxResult CalcularNfe(NfeTaxContext ctx);
    NfceTaxResult CalcularNfce(NfceTaxContext ctx);
    NfseTaxResult CalcularNfse(NfseTaxContext ctx);
}
```

### 4.2 Contexto de entrada — NF-e / NFC-e (`NfeTaxContext`)

```
NfeTaxContext
  regimeTributario: RegimeTributario
  cfop: CodigoCfop
  valorBruto: ValorMonetario
  valorDesconto: ValorMonetario          # desconto incondicionado
  quantidadeComercial: decimal

  # ICMS
  cst: string                            # CST (Regime Normal) ou CSOSN (Simples Nacional)
  origemMercadoria: int                  # 0=Nacional, 1–8=Importada (tabela A)
  modalidadeBcIcms: int                  # 0=MargemValorAgregado, 1=Pauta, 2=PrecoTabelado, 3=ValorOperacao
  aliquotaIcms: Aliquota
  reducaoBcIcms: Aliquota                # % redução da base de cálculo
  # ST (substituição tributária)
  modalidadeBcSt: int?
  mvaAjustado: Aliquota?
  aliquotaIcmsSt: Aliquota?
  reducaoBcSt: Aliquota?
  # FCP
  aliquotaFcp: Aliquota                  # Fundo Combate Pobreza — obrigatório se > 0
  aliquotaFcpSt: Aliquota?
  # DIFAL (interestadual consumidor final)
  isDifalApplicavel: bool
  ufEmitente: string                     # 2 letras
  ufDestinatario: string
  aliquotaInterestadual: Aliquota        # alíquota da operação interestadual
  aliquotaInternaUfDest: Aliquota        # alíquota interna UF destino

  # IPI
  cstIpi: string?                        # null se não aplicável
  cnpjProdutor: Cnpj?
  codigoSeloIpi: string?
  aliquotaIpi: Aliquota?

  # PIS / COFINS
  cstPis: string                         # tabela PIS/COFINS
  aliquotaPis: Aliquota
  cstCofins: string
  aliquotaCofins: Aliquota

  # IBS / CBS — obrigatório para Regime Normal desde 03/08/2026 (NT 2025.002 v1.40)
  ibsCbsApplicavel: bool
  cstIbsCbs: string?                     # tabela IBS/CBS da NT 2025.002
  cClassTrib: string?                    # classificação tributária IBS/CBS
  aliquotaIbs: Aliquota?
  aliquotaCbs: Aliquota?
  cIndOp: string?                        # Indicador local operação de fornecimento (B25d)
  isZonaFrancaManaus: bool               # indZFMALC — NT 007/2026

  # Imposto Seletivo
  isAplicavel: bool
  aliquotaIs: Aliquota?
```

### 4.3 Regras de cálculo — ICMS

**CST 00 — Tributada integralmente:**
```
BC_ICMS = (valorBruto - desconto) * (1 - reducaoBcIcms/100)
vICMS   = BC_ICMS * aliquotaIcms / 100
vFCP    = BC_ICMS * aliquotaFcp / 100
```

**CST 10 — Tributada com ST:**
```
BC_ICMS = igual ao CST 00
vICMS   = BC_ICMS * aliquotaIcms / 100
BC_ST   = (BC_ICMS * (1 + mvaAjustado/100)) * (1 - reducaoBcSt/100)
vICMSST = (BC_ST * aliquotaIcmsSt/100) - vICMS
vFCPST  = BC_ST * aliquotaFcpSt/100
```

**CST 20 — Com redução de BC:**
```
BC_ICMS = (valorBruto - desconto) * (1 - reducaoBcIcms/100)
vICMS   = BC_ICMS * aliquotaIcms / 100
```

**CST 30 — Isenta/NT com ST:**
```
vICMS   = 0
BC_ST   = ((valorBruto - desconto) * (1 + mvaAjustado/100)) * (1 - reducaoBcSt/100)
vICMSST = BC_ST * aliquotaIcmsSt/100
```

**CST 40/41/50 — Isenta / NT / Suspensão:**
```
vICMS = 0, todos os campos de BC = 0
```

**CST 51 — Diferimento:**
```
BC_ICMS   = (valorBruto - desconto) * (1 - reducaoBcIcms/100)
vICMSOp   = BC_ICMS * aliquotaIcms / 100      # imposto da operação
vICMSDif  = vICMSOp * percentualDiferimento / 100
vICMS     = vICMSOp - vICMSDif
```

**CST 60 — ICMS cobrado anteriormente por ST (substituído):**
```
Campos vBCSTRet e vICMSSTRet obrigatórios (valores da retenção anterior)
vICMS = 0
pST obrigatório se venda a consumidor final (percentual ST suportado)
```

**CST 70 — Tributada com ST e redução de BC:**
```
Combina lógica de CST 20 (redução) + CST 10 (ST)
```

**CST 90 — Outros:**
```
Campos conforme parametrização manual — lib não infere, ERP deve fornecer todos os campos
```

**CSOSN (Simples Nacional):**
```
CSOSN 101 — Crédito SN: informar alíquotaCredito e vCredICMSSN (não gera ICMS normal)
CSOSN 102/300/400 — Sem crédito: nenhum campo de valor
CSOSN 201 — ST com crédito: calcular ST igual CST 10 + informar crédito SN
CSOSN 202/203 — ST sem crédito: calcular ST igual CST 10
CSOSN 500 — ST anteriormente retida: igual CST 60
CSOSN 900 — Outros: regime normal simplificado, campos completos
```

**DIFAL interestadual para consumidor final (EC 87/2015 — regra permanente pós-partilha):**
```
Aplica quando: indFinal=1 (consumidor final) E indIEDest=9 (não contribuinte) E UF emitente != UF destino
vICMSDif  = (aliquotaInternaUfDest - aliquotaInterestadual) * BC_ICMS / 100
vICMSUFRem = vICMSDif * (aliquotaInternaUfDest - aliquotaInterestadual) / aliquotaInternaUfDest  # parcela estado remetente
vICMSUFDest= vICMSDif - vICMSUFRem
vFCPUFDest = BC_ICMS * aliquotaFcpUfDest / 100
```

### 4.4 Regras de cálculo — IPI

```
Condições para informar IPI:
  - CFOP de saída (série 5xxx, 6xxx, 7xxx)
  - Produto industrializado (NCM aplicável)
  - Operação não isenta

BC_IPI = valorBruto - desconto
vIPI   = BC_IPI * aliquotaIpi / 100

CST IPI:
  00 = Entrada com recuperação de crédito
  49 = Outras entradas
  50 = Saída tributada
  99 = Outras saídas
  01/02/03/04 = Entradas com isenção/ST/NT
  51-55 = Saídas com isenção/ST/NT/suspensão/imunidade
```

### 4.5 Regras de cálculo — PIS e COFINS

```
Alíquotas padrão (Lucro Presumido, regime cumulativo):
  PIS    = 0,65%
  COFINS = 3,00%

Alíquotas padrão (Lucro Real, regime não-cumulativo):
  PIS    = 1,65%
  COFINS = 7,60%

Simples Nacional:
  PIS e COFINS = 0 (tributação unificada no DAS)
  CST PIS/COFINS = 07 (operação isenta da contribuição)

BC_PIS    = valorBruto - desconto
vPIS      = BC_PIS * aliquotaPis / 100

BC_COFINS = valorBruto - desconto
vCOFINS   = BC_COFINS * aliquotaCofins / 100

CST PIS/COFINS relevantes:
  01 = Operação tributável (BC = valor operação, alíquota normal)
  02 = Operação tributável (BC = valor operação, alíquota diferenciada)
  03 = Operação tributável (BC = quantidade vendida)
  04 = Operação com incidência monofásica
  05 = Operação tributável por substituição tributária
  06 = Operação de alienação de direito creditório vincendo
  07 = Operação isenta da contribuição
  08 = Operação sem incidência da contribuição
  09 = Operação com suspensão da contribuição
  49 = Outras operações de saída
  50-56 = Operações com direito a crédito
  60-66 = Créditos de operações específicas
  70-75 = Operações de aquisição vinculadas a operações de exportação
  98 = Outras operações de entrada
  99 = Outras operações

ATENÇÃO NT 007/2026:
  vPis e vCofins = valores DEVIDOS na operação, NUNCA valores retidos
  Valores retidos vão em: vRetCP, vRetIRRF, vRetCSLL (campo consolidado conforme tpRetPisCofins)
```

### 4.6 Regras de cálculo — IBS, CBS e IS (NT 2025.002 v1.40 — obrigatório Regime Normal desde 03/08/2026)

```
Característica fundamental: IBS e CBS são tributos "por fora"
  Seu valor SOMA ao total da nota (diferente do ICMS que está embutido no preço)

BC_IBS = BC_CBS = valorBruto - desconto  # mesma base

# IBS: imposto estadual/municipal (substitui ICMS e ISS)
vIBS = BC_IBS * aliquotaIbs / 100

# CBS: contribuição federal (substitui PIS/COFINS)
vCBS = BC_CBS * aliquotaCbs / 100

# Imposto Seletivo (IS) — incide sobre bens/serviços específicos
vIS = BC_IS * aliquotaIs / 100

Simples Nacional e MEI:
  Obrigatoriedade começa em 04/01/2027 (prazos NF-e/NFC-e)
  Para NFS-e: grupo IBSCBS obrigatório desde 01/08/2026 mas optantes SN têm tratamento diferenciado

Zona Franca de Manaus (NT 007/2026):
  campo indZFMALC = true → alíquota zero de CBS para operações previstas na LC 214/2025

cClassTrib (classificação tributária IBS/CBS) — campo obrigatório junto com cstIbsCbs:
  000001 = Tributação integral
  000002 = Redução de alíquota
  ... (tabela completa publicada no portal NF-e)

cIndOp (B25d — NT 2025.002 v1.40):
  Código indicador do local da operação de fornecimento — obrigatório quando aplicável

Arredondamento: bancário (round half to even), tolerância de R$ 0,01 (NT 007/2026)
```

### 4.7 Regras de cálculo — ISS (NFS-e Nacional)

```
Simples Nacional (MEI / ME / EPP):
  ISS embutido no DAS — alíquota não precisa ser informada na DPS em maioria dos casos
  Exceção: ISS recolhido fora do DAS (e.g. retido pelo tomador) → informar alíquota e valor
  Campo optanteSimplesNacional: 2 (MEI) ou 3 (ME/EPP)
  Campos PIS/COFINS/CSLL/IRRF ficam zerados

Regime Normal (Lucro Presumido / Lucro Real):
  BC_ISS = vServPrest - vDescCondIncond - vDescIncond
  vISS   = BC_ISS * aliquotaISS / 100
  alíquota definida pelo município (cMunFG — código IBGE do município onde o serviço é prestado)
  Alíquota mínima nacional: 2% (LC 116/2003, art. 8-A)
  Alíquota máxima nacional: 5%

Retenção ISS pelo tomador:
  Quando indIssRet = true:
    vISSRet = vISS
    vLiq    = vServ - vISSRet - vRetCP - vRetIRRF - vRetCSLL

PIS (Lucro Presumido): 0,65% sobre vServ
COFINS (Lucro Presumido): 3,00% sobre vServ
IRRF: alíquota conforme tabela de serviços (geralmente 1,5%)
CSLL: 1% sobre vServ (quando retido)
CP (INSS tomador): 11% sobre vServ (serviços específicos, art. 31 Lei 8.212/91)

ATENÇÃO NT 007/2026:
  tpRetPisCofins expandido (0-7): usar combinação correta de retenção
  vRetCSLL consolidado: quando reter PIS+COFINS+CSLL, somar tudo em vRetCSLL
  vPis e vCofins = valores DEVIDOS, não retidos

IBS/CBS na NFS-e (NT 003-009/2025-2026):
  Grupo IBSCBS obrigatório desde 01/08/2026
  Na DPS o contribuinte declara: CST e cClassTrib
  As alíquotas e valores são calculados pelo Ambiente Nacional (ADN/SEFIN) — NÃO enviar valores
  finNFSe: 0=Normal, 1=Crédito, 2=Débito (NT 009/2026)
  Notas de ajuste: grupo gIBSCBSAjuste para ajuste de crédito/débito IBS/CBS
```

---

## 5. Builder NF-e (`Nfe/NfeBuilder`)

O builder constrói um `NfePayload` tipado. Usa fluent interface e valida obrigatoriedade em `build()`.

### 5.1 Grupos do XML NF-e 4.00 e mapeamento para o builder

#### Identificação (infNFe/ide)

| Campo XML | Nome | Obrigatório | Validação |
|-----------|------|-------------|-----------|
| cUF | UF emitente (código IBGE) | S | 2 letras → código |
| cNF | Código numérico NF | S | 8 dígitos, gerado aleatório se omitido |
| natOp | Natureza da operação | S | 1-60 caracteres |
| mod | Modelo | S | fixo = 55 |
| serie | Série | S | 0-999 |
| nNF | Número da NF | S | 1-999999999 |
| dhEmi | Data/hora emissão | S | UTC, formato ISO-8601 |
| dhSaiEnt | Data/hora saída/entrada | N | UTC se informado |
| tpNF | Tipo (entrada=0/saída=1) | S | enum TipoOperacao |
| idDest | Destino (1=interna,2=interestadual,3=exterior) | S | |
| cMunFG | Município fato gerador | S | 7 dígitos IBGE |
| tpImp | Tipo impressão DANFE | S | 1=paisagem, 2=retrato |
| tpEmis | Tipo emissão | S | 1=normal, 2-9=contingências |
| cDV | Dígito verificador chave | S | calculado automaticamente pela lib |
| tpAmb | Ambiente | S | enum AmbienteSEFAZ |
| finNFe | Finalidade | S | enum FinalidadeNfe |
| indFinal | Consumidor final | S | 0=não, 1=sim |
| indPres | Indicador presença | S | enum IndicadorPresenca |
| indIntermed | Intermediador | N | 0=sem, 1=com intermediador |
| procEmi | Processo emissão | S | 0=app contribuinte |
| verProc | Versão processo | S | string livre |

#### Emitente (emit)

| Campo XML | Obrigatório | Regra |
|-----------|-------------|-------|
| CNPJ/CPF | S | CNPJ ou CPF — alfanumérico a partir jul/2026 |
| xNome | S | 2-60 chars |
| xFant | N | |
| enderEmit completo | S | logradouro, nro, bairro, cMun, xMun, UF, CEP, cPais, xPais, fone |
| IE | S | exceto para MEI e pessoa física |
| IEST, IM, CNAE | N | |
| CRT | S | 1=SN Microempresa, 2=SN Excesso, 3=Regime Normal |

#### Destinatário (dest)

| Campo XML | Obrigatório | Regra |
|-----------|-------------|-------|
| CNPJ/CPF/idEstrangeiro | S | um dos três |
| xNome | S* | obrigatório exceto NFC-e sem identificação |
| email | N | máx 60 chars |
| enderDest | S para NF-e, N para NFC-e sem dest | completo |
| indIEDest | S | 1=contribuinte, 2=isento, 9=não contribuinte |
| IE | S se indIEDest=1 | |

#### Itens (det[])

Cada item tem:

**Produto (prod):**

| Campo | Obrigatório | Regra |
|-------|-------------|-------|
| cProd | S | código interno |
| cEAN | S | GTIN ou "SEM GTIN" |
| xProd | S | 1-120 chars |
| NCM | S | 8 dígitos |
| CEST | N | obrigatório se produto sujeito a ST (conv. ICMS 92/2015) |
| CFOP | S | 4 dígitos |
| uCom | S | unidade comercial |
| qCom | S | quantidade comercial |
| vUnCom | S | valor unitário comercial (10 decimais) |
| vProd | S | valor total bruto |
| cEANTrib | S | GTIN tributável ou "SEM GTIN" |
| uTrib | S | unidade tributável |
| qTrib | S | quantidade tributável |
| vUnTrib | S | valor unitário tributável |
| vFrete, vSeg, vDesc, vOutro | N | |
| indTot | S | 0=não compõe total, 1=compõe total |
| xPed, nItemPed | N | referência pedido |
| nFCI | N | número FCI (conteúdo importado) |

**Impostos do item (imposto):** ver seção 4.

#### Totais (total/ICMSTot)

Calculado automaticamente pelo builder somando todos os itens:

```
vBC, vICMS, vICMSDeson, vFCP, vBCST, vST, vFCPST, vFCPSTRet,
vProd, vFrete, vSeg, vII, vIPI, vIPIDevol, vPIS, vCOFINS,
vDesc, vOutro, vNF (= vProd - vDesc + vST + vFrete + vSeg + vII + vIPI + vPIS + vCOFINS + vOutro)
vTotTrib (estimativa total de tributos — obrigatório, pode usar tabela IBPT)

# IBS/CBS/IS (NT 2025.002 v1.40 — obrigatório desde 03/08/2026 Regime Normal)
vIBS, vCBS, vIS
```

**Regra crítica de totais (rejeição 610):**
`vNF` deve ser exatamente igual ao somatório dos valores dos itens com `indTot=1`. Tolerância: R$ 0,01.

#### Transporte (transp)

| Campo | Obrigatório | Regra |
|-------|-------------|-------|
| modFrete | S | 0=emitente, 1=dest/rem, 2=terceiros, 3=próprio dest, 4=próprio rem, 9=sem frete |
| transporta | N | CNPJ ou CPF do transportador |
| veicTransp, reboque | N | |
| vol[] | N | volumes |

#### Cobrança (cobr) — N

Duplicatas e fatura. Obrigatório se houver pagamento a prazo.

#### Pagamento (pag) — obrigatório NFC-e, S para NF-e

```
detPag[]:
  tPag: 01=dinheiro, 02=cheque, 03=cartão crédito, 04=cartão débito,
        05=crédito loja, 10=vale alimentação, 11=vale refeição, 12=vale presente,
        13=vale combustível, 14=duplicata mercantil, 15=boleto, 90=sem pagamento,
        99=outros
  vPag: valor do pagamento
  indPag: 0=à vista, 1=a prazo
  tpIntegra (NFC-e): 1=pagamento integrado, 2=não integrado
  CNPJ/CPF credenciadora, tBand, cAut (para cartão)
vTroco: troco (NFC-e)
```

#### Informações Adicionais (infAdic)

```
infAdFisco: informações ao fisco (1-2000 chars)
infCpl: informações complementares (1-5000 chars)
```

#### Exportação (exporta) — condicional CFOP 7xxx

#### Compra (compra) — N

#### Cana (cana) — específico setor sucroalcooleiro — N

#### Responsável Técnico (infRespTec) — obrigatório

```
CNPJ do desenvolvedor, xContato, email, fone, hashCSRT, idCSRT
```

### 5.2 Regras de validação do NfeBuilder

```
R001: CFOP de saída (5xxx/6xxx/7xxx) → tpNF = 1 (saída)
R002: CFOP de entrada (1xxx/2xxx/3xxx) → tpNF = 0 (entrada)
R003: CFOP 7xxx → exportação, idDest = 3
R004: indFinal = 1 E indIEDest = 9 E UF diferente → calcular DIFAL
R005: CRT = 1 ou 2 → usar CSOSN; CRT = 3 → usar CST
R006: vNF deve bater com somatório itens (tolerância ±0,01)
R007: Se CEST informado → NCM deve estar na lista de produtos sujeitos a ST
R008: IPI só para CFOP de industrialização/importação
R009: Regime Normal → IBS/CBS obrigatório desde 03/08/2026 (ibsCbsApplicavel = true)
R010: Simples Nacional → IBS/CBS obrigatório desde 04/01/2027
R011: vPis/vCofins = valores devidos, nunca retidos (NT 007/2026)
R012: Número de NF deve ser sequencial por série — lib não controla sequência, ERP deve garantir
R013: dhEmi não pode ser mais de 5 minutos no futuro (validação SEFAZ)
R014: Email destinatário máx 60 chars
R015: xProd máx 120 chars
R016: Para operação com intermediador (indIntermed=1) → obrigatório infIntermed (CNPJ e idCadIntTran)
```

---

## 6. Builder NFC-e (`Nfce/NfceBuilder`)

NFC-e herda quase tudo do NfeBuilder. Diferenças explícitas:

### 6.1 Diferenças estruturais

```
mod = 65 (fixo)
tpImp = 4 (DANFE NFC-e)
idDest = 1 (sempre operação interna — venda presencial)
```

### 6.2 Destinatário — opcional

```
NFC-e sem destinatário identificado: omitir grupo dest completamente
NFC-e com CPF: informar CPF e xNome (opcional)
NFC-e com CNPJ: informar CNPJ e xNome — permitido conforme Ajuste SINIEF 12/2026
Limite sem identificação: R$ 10.000,00 (acima deste valor identificação obrigatória)
```

### 6.3 QR Code — gerado pela API .NET

A lib não gera o QR Code, mas deve incluir no payload:
```
urlChave: URL de consulta (ambiente Produção ou Homologação)
idToken: identificador do CSC (Código de Segurança do Contribuinte) — fornecido pelo ERP
hashCSC: SHA-1 de (chaveAcesso + idToken + CSC) — a API .NET calcula, lib envia os ingredientes
versaoQrCode: 3 (NT 2025.001 — QR Code versão 3 a partir de 2025)
```

### 6.4 Pagamento — obrigatório NFC-e

```
Ao menos um detPag obrigatório
tpIntegra obrigatório para cartão
vTroco obrigatório quando tPag = dinheiro e vPag > vNF
Regra: somatório vPag >= vNF (tolerância ±0,01)
```

### 6.5 Validações específicas NFC-e

```
R-NFC001: vNF máx R$ 200.000,00 por nota
R-NFC002: Sem destinatário → vNF máx R$ 10.000,00
R-NFC003: Série obrigatória entre 000 e 899 (900-999 reservado para contingência offline)
R-NFC004: Não admite IPI (CST IPI não se aplica)
R-NFC005: indPres deve ser 1 (presencial) ou 4 (delivery domicílio)
R-NFC006: Contingência: tpEmis = 9 (offline), dhCont e xJust obrigatórios
R-NFC007: Não admite carta de correção (CC-e) — somente cancelamento
R-NFC008: Prazo cancelamento: consultar prazo estadual (via API status), padrão 30 minutos em SP
```

---

## 7. Builder NFS-e Nacional (`Nfse/NfseBuilder`)

### 7.1 Estrutura DPS completa (schema v1 + NT 004 v2.00 + NT 007/2026 + NT 009/2026)

```
DPS
└── infDPS  [Id="DPS{44_chars}"]
    ├── tpAmb           Ambiente (1=Prod, 2=Homolog)
    ├── dhEmi           Data/hora emissão UTC
    ├── dCompet         Data competência AAAA-MM-DD
    ├── verAplic        Versão aplicativo emitente
    ├── serie           Série (A-Z + 0-9, max 5)
    ├── nDPS            Número sequencial DPS (1-15 dígitos)
    ├── cLocEmi         Código município emitente (7 dígitos IBGE)
    ├── subst           Grupo substituição (opcional)
    │   ├── chSubstda   Chave NFS-e a substituir (50 chars)
    │   └── cMotivo     Código motivo substituição (01-09)
    ├── prest           Prestador
    │   ├── CNPJ/CPF    CNPJ alfanumérico (NT 009/2026) ou CPF
    │   ├── IM          Inscrição Municipal (obrigatório se CNPJ)
    │   └── regTrib     Regime tributário
    │       ├── opSimpNac  Optante Simples (0=não, 1=MEI, 2=ME/EPP)
    │       ├── regApTribSN   Regime apuração SN (1=competência, 2=caixa)
    │       ├── regEspTrib    Regime especial (0-6)
    │       └── porte      Porte empresa (00=sem, 01=MEI, 02=ME, 03=EPP, 04=demais)
    ├── toma            Tomador
    │   ├── CNPJ/CPF/NIF estrangeiro
    │   ├── xNome
    │   ├── end         Endereço completo
    │   └── email, fone
    ├── serv            Serviço
    │   ├── locPrest    Local prestação
    │   │   ├── cLocPrestacao  Código município prestação (7 dígitos)
    │   │   └── cPaisPrestacao Código país (se exterior)
    │   ├── cServ       Código serviço
    │   │   ├── cTribNac  Código tribut. nacional (LC 116)
    │   │   ├── cTribMun  Código municipal (tabela prefeitura)
    │   │   ├── CNAE      Código CNAE
    │   │   └── cNBS      Código NBS (9 dígitos) — obrigatório no padrão nacional
    │   ├── xDescServ   Descrição serviço (1-2000 chars)
    │   └── qtde        Quantidade (quando aplicável)
    ├── valores
    │   ├── vServPrest
    │   │   ├── vReceb  Valor recebido/a receber
    │   │   └── vServ   Valor bruto do serviço
    │   ├── vDescCondIncond
    │   │   ├── vDescIncond   Desconto incondicionado
    │   │   └── vDescCond     Desconto condicionado
    │   └── trib        Tributação
    │       ├── tribMun ISS
    │       │   ├── cLocIncid    Município incidência ISS (7 dígitos)
    │       │   ├── cPaisResult  País resultado (exterior)
    │       │   ├── BM           Base de cálculo ISS
    │       │   ├── exigSusp     Exigibilidade suspensa (0=não, 1=sim)
    │       │   │   └── tpSusp / nProcesso
    │       │   ├── tpImunidade  Tipo imunidade (0=sem, 1-4=tipos)
    │       │   ├── pAliq        Alíquota ISS
    │       │   ├── tpRetISSQN   Retenção ISS (1=retido, 2=não retido)
    │       │   └── vISSQN       Valor ISS
    │       ├── tribFed  Tributos Federais
    │       │   ├── vRetCP       Valor retido CP/INSS
    │       │   ├── vRetIRRF     Valor retido IRRF
    │       │   ├── vRetCSLL     Valor retido CSLL+PIS+COFINS (consolidado NT 007/2026)
    │       │   ├── vPIS         Valor PIS DEVIDO (não retido)
    │       │   ├── vCOFINS      Valor COFINS DEVIDO (não retido)
    │       │   ├── tpRetPisCofins  Tipo retenção expandido (0-7, NT 007/2026)
    │       │   └── pTotTribFed  Percentual total tributos federais (opcional)
    │       └── totTrib  Totais
    │           ├── vTotTrib  Total tributos
    │           └── pTotTrib  Percentual total (opcional, para IBPT)
    └── IBSCBS      Grupo IBS/CBS — obrigatório desde 01/08/2026 (NT 003-009)
        ├── cIndOp      Indicador operação (tabela Anexo VII NT 003/2025)
        ├── CST         Código situação tributária IBS/CBS
        ├── cClassTrib  Classificação tributária
        ├── indZFMALC   Zona Franca Manaus alíquota zero CBS (NT 007/2026)
        └── finNFSe     Finalidade (0=normal, 1=crédito, 2=débito — NT 009/2026)
```

### 7.2 Chave de acesso NFS-e Nacional (50 posições)

```
Estrutura:
[2 cUF] [6 AAMM] [14 CNPJ emitente] [5 serie] [15 nDPS] [1 tpEmis] [6 cNF] [1 cDV]

cUF: código IBGE da UF (2 dígitos)
AAMM: ano-mês competência
CNPJ: 14 dígitos (alfanumérico pós jul/2026)
serie: série da DPS (5 chars, completar com zeros à esquerda)
nDPS: número da DPS (15 dígitos, completar com zeros à esquerda)
tpEmis: tipo emissão (1=normal)
cNF: código numérico aleatório 6 dígitos
cDV: dígito verificador (módulo 11)

ATENÇÃO NT 009/2026: CNPJ alfanumérico a partir de julho de 2026
  Todos os campos CNPJ convertidos de numérico para tipo C (caractere)
```

### 7.3 Validações específicas NFS-e

```
R-NFS001: cNBS obrigatório — código NBS de 9 dígitos
R-NFS002: cLocIncid (município incidência ISS) pode diferir de cLocEmi — é o município onde o serviço foi prestado
R-NFS003: Simples Nacional → opSimpNac = 1 (MEI) ou 2 (ME/EPP)
R-NFS004: tpRetISSQN = 1 (retido) → vISSQN obrigatório e igual ao ISS calculado
R-NFS005: exigSusp = 1 → tpSusp e nProcesso obrigatórios
R-NFS006: Grupo IBSCBS obrigatório desde 01/08/2026 — lib deve emitir erro se ausente
R-NFS007: Não enviar valores de IBS/CBS na DPS — apenas CST e cClassTrib; valores são calculados pelo ADN
R-NFS008: vPIS/vCOFINS = valores devidos, não retidos (NT 007/2026)
R-NFS009: tpRetPisCofins = 3 (PIS+COFINS) ou 4 (PIS+COFINS+CSLL) → vRetCSLL = soma dos três
R-NFS010: substituição → chSubstda e cMotivo obrigatórios
R-NFS011: finNFSe aceita 0/1/2 a partir da NT 009/2026
R-NFS012: CNPJ alfanumérico aceito a partir de jul/2026 (NT 009/2026)
R-NFS013: Regime especial cooperativa → regEspTrib = 1
R-NFS014: Exportação de serviços → cPaisResult obrigatório, ISS = 0 (exigSusp ou exigibilidade exportação)
R-NFS015: Imunidade → tpImunidade informado, pAliq = 0, vISSQN = 0
R-NFS016: indZFMALC = true → operações ZFM/ALC com CBS alíquota zero (NT 007/2026)
```

---

## 8. Serialização do Contrato HTTP (`Http/PayloadSerializer`)

> **⚠️ ATUALIZAÇÃO (2026-09-07):** os payloads desta seção foram substituídos pelo
> contrato real da FiscalAPI (ver nota na seção 1.3). A lib serializa
> `EmissaoRequest`/`NfseDpsRequest` (campos camelCase, decimais como números JSON)
> e consome `EmissaoResponse` + polling. O bloco a seguir permanece apenas como
> registro histórico da spec original.

### 8.1 Request para API .NET

```json
{
  "documento": "nfe" | "nfce" | "nfse",
  "ambiente": 1 | 2,
  "versao_layout": "4.00" | "1.00",
  "emitente": {
    "cnpj": "string (alfanumérico)",
    "regime": 1 | 2 | 3,
    "certificado_id": "string"
  },
  "tributos_calculados": {
    /* NfeTaxResult ou NfseTaxResult serializado */
  },
  "payload": {
    /* NfePayload | NfcePayload | NfsePayload serializado em JSON */
  },
  "metadata": {
    "lib_version": "string",
    "emitido_em": "ISO-8601 UTC",
    "idempotency_key": "uuid-v4"
  }
}
```

**`idempotency_key`:** UUID gerado pela lib a cada chamada — permite que a API .NET deduplicar reenvios.

### 8.2 Response da API .NET (`NfResponse`)

```json
{
  "sucesso": true | false,
  "documento": "nfe" | "nfce" | "nfse",
  "chave": "string (44 ou 50 chars)",
  "numero": "string",
  "serie": "string",
  "status": "autorizada" | "rejeitada" | "cancelada" | "processando" | "contingencia",
  "protocolo": "string",
  "data_autorizacao": "ISO-8601 UTC",
  "xml_autorizado": "string base64",
  "pdf_base64": "string base64 | null",
  "erros": [
    {
      "codigo": "string",
      "mensagem": "string",
      "campo": "string | null",
      "reversivel": true | false
    }
  ],
  "avisos": ["string"],
  "ambiente": 1 | 2
}
```

### 8.3 Request cancelamento

```json
{
  "documento": "nfe" | "nfse",
  "chave": "string",
  "justificativa": "string (15-255 chars)",
  "ambiente": 1 | 2,
  "emitente": { "cnpj": "string", "certificado_id": "string" }
}
```

### 8.4 Request substituição NFS-e

```json
{
  "documento": "nfse",
  "ambiente": 1 | 2,
  "chave_substituida": "string (50 chars)",
  "codigo_motivo": "01-09",
  "emitente": { "cnpj": "string", "certificado_id": "string", "regime": 3 },
  "tributos_calculados": { /* NfseTaxResult */ },
  "payload": { /* NfsePayload nova nota */ }
}
```

---

## 9. Códigos de Motivo de Substituição NFS-e

| Código | Descrição |
|--------|-----------|
| 01 | Desenquadramento de NFS-e do Simples Nacional |
| 02 | Enquadramento de NFS-e no Simples Nacional |
| 03 | Inclusão retroativa de imunidade/isenção |
| 04 | Exclusão retroativa de imunidade/isenção |
| 05 | Substituição em virtude de decisão judicial |
| 06 | Substituição por erro de tomador |
| 07 | Substituição por erro de prestador |
| 08 | Substituição de nota emitida com código de serviço incorreto |
| 09 | Outros |

---

## 10. Tabela de Exigibilidade ISS

| Código | Descrição | Preencher ISS? |
|--------|-----------|----------------|
| 1 | Exigível | Sim |
| 2 | Não incidência | Não (0) |
| 3 | Isenção | Não (0) |
| 4 | Exportação | Não (0) |
| 5 | Imunidade | Não (0) |
| 6 | Exigibilidade suspensa — decisão judicial | Informar processo |
| 7 | Exigibilidade suspensa — processo administrativo | Informar processo |

---

## 11. Validações Comuns a NF-e e NFS-e

```
V001: CNPJ alfanumérico — aceitar formato novo a partir de julho de 2026 (NT 009/2026)
V002: Dígito verificador de chave de acesso — validação ativa sempre
V003: Valores monetários com exatamente 2 casas decimais (exceto vUnCom/vUnTrib: 10 casas)
V004: Arredondamento bancário (round half to even) em todos os cálculos — tolerância R$ 0,01
V005: Datas em UTC, formato ISO-8601 com timezone
V006: IBS/CBS obrigatório Regime Normal: falha em build() se ibsCbsApplicavel=true e campos ausentes
V007: vPIS/vCOFINS nunca podem representar valores retidos — erro de validação se campo mal usado
V008: Campos condicionais: validar presença conforme regime, CFOP, CST e tipo de operação
V009: idempotency_key deve ser único por emissão — lib gera UUID v4 automaticamente
```

---

## 12. Tratamento de Erros

### 12.1 Hierarquia de exceções (PHP)

```php
FiscalLibException (base)
├── ValidationException          # regras de negócio violadas antes de enviar
│   ├── MissingFieldException    # campo obrigatório ausente
│   ├── InvalidValueException    # valor fora do domínio esperado
│   └── TaxInconsistencyException # inconsistência entre regime/CST/valores
├── TaxCalculationException      # erro no motor tributário
├── SerializationException       # falha ao montar o JSON de request
└── ApiResponseException         # resposta de erro da API .NET
    ├── RejectionException       # SEFAZ rejeitou (erros[].reversivel)
    └── ApiUnavailableException  # timeout ou erro HTTP
```

### 12.2 Códigos de rejeição SEFAZ mais comuns a tratar

| Código | Causa | Reversível |
|--------|-------|------------|
| 610 | Total NF difere somatório itens | Sim |
| 539 | CSRT inválido | Sim |
| 999 | Erro interno SEFAZ | Sim |
| 775 | Modelo NFC-e diferente de 65 | Sim (bug builder) |
| 656 | Rejeição: CSC inválido (NFC-e) | Sim |
| 238 | CNPJ emitente não cadastrado | Não |
| 204 | Duplicidade de NF (mesmo número+série) | Não |

---

## 13. Configuração da Biblioteca

```php
// PHP — inicialização
$config = FiscalConfig::make()
    ->ambiente(AmbienteSEFAZ::Homologacao)
    ->apiBaseUrl('https://api-fiscal.internal/v1')
    ->apiTimeout(30)           // segundos
    ->ibsObrigatorioDesde(Carbon::parse('2026-08-03'))  // Regime Normal
    ->ibsObrigatorioSNDesde(Carbon::parse('2027-01-04')); // Simples Nacional

$lib = new FiscalLib($config);
```

```csharp
// C# — via DI (ASP.NET Core)
services.AddFiscalLib(options => {
    options.Ambiente = AmbienteSEFAZ.Homologacao;
    options.ApiBaseUrl = "https://api-fiscal.internal/v1";
    options.ApiTimeoutSeconds = 30;
    options.IbsObrigatorioDesde = new DateOnly(2026, 8, 3);
    options.IbsObrigatorioSNDesde = new DateOnly(2027, 1, 4);
});
```

---

## 14. Exemplos de Uso

### 14.1 Emissão NF-e — Regime Normal, saída interestadual

```php
$ctx = NfeTaxContext::make()
    ->regime(RegimeTributario::RegimeNormal)
    ->cfop('6102')
    ->valorBruto(1000.00)
    ->cst('00')->aliquotaIcms(12.0)->modalidadeBcIcms(3)
    ->aliquotaFcp(2.0)
    ->cstPis('01')->aliquotaPis(1.65)
    ->cstCofins('01')->aliquotaCofins(7.60)
    ->ibsCbs(cst: '000', cClassTrib: '000001', aliquotaIbs: 3.5, aliquotaCbs: 9.25);

$tributos = $lib->taxEngine()->calcularNfe($ctx);

$payload = $lib->nfe()->builder()
    ->ide(fn($i) => $i
        ->natOp('Venda de mercadoria')
        ->serie(1)->nNF(12345)
        ->dhEmi(now())
        ->tpNF(TipoOperacao::Saida)
        ->idDest(2)  // interestadual
        ->cMunFG('4106902')
        ->indFinal(TipoConsumidorFinal::Normal)
        ->indPres(IndicadorPresenca::NaoSeAplica)
        ->finNFe(FinalidadeNfe::Normal)
    )
    ->emitente(fn($e) => $e->fromEmpresa($empresa))
    ->destinatario(fn($d) => $d->fromCliente($cliente))
    ->addItem(fn($it) => $it
        ->produto($produto)
        ->quantidade(10)
        ->valorUnitario(100.00)
        ->tributos($tributos)
    )
    ->transporte(fn($t) => $t->modFrete(0))
    ->pagamento(fn($p) => $p->addParcela('01', 1000.00))
    ->build();

$response = $lib->nfe()->emitir($payload);

if ($response->sucesso) {
    // persistir $response->chave, $response->xml_autorizado, $response->pdf_base64
}
```

### 14.2 Emissão NFS-e — Lucro Presumido, ISS retido

```php
$ctx = NfseTaxContext::make()
    ->regime(RegimeTributario::RegimeNormal)
    ->valorServico(5000.00)
    ->aliquotaISS(3.0)
    ->retencaoISS(true)
    ->aliquotaPis(0.65)->aliquotaCofins(3.0)
    ->tipoRetencaoPisCofins(TipoRetencaoPisCofins::SemRetencao)
    ->aliquotaIRRF(1.5)
    ->ibsCbs(cst: '000', cClassTrib: '000001', cIndOp: '01');

$tributos = $lib->taxEngine()->calcularNfse($ctx);

$payload = $lib->nfse()->builder()
    ->prestador(fn($p) => $p->fromEmpresa($empresa))
    ->tomador(fn($t) => $t->fromCliente($cliente))
    ->servico(fn($s) => $s
        ->nbs('1.05.01.00.00')
        ->codigoLC116('1.01')
        ->descricao('Consultoria em tecnologia da informação')
        ->municipioPrestacao('4106902')
    )
    ->competencia('2026-08')
    ->tributos($tributos)
    ->build();

$response = $lib->nfse()->emitir($payload);
```

### 14.3 Cancelamento NFS-e

```php
$response = $lib->nfse()->cancelar(
    chave: '50260800000000000180A0001000000001100000012',
    justificativa: 'Nota emitida com dados incorretos do tomador',
    ambiente: AmbienteSEFAZ::Producao
);
```

---

## 15. Configurações de Serviço no ERP (alimentam a lib)

Para que o `TaxEngine` funcione sem acesso a banco, o ERP deve fornecer por serviço/produto:

```
ServicoFiscalConfig:
  nbs: CodigoNbs
  codigoLC116: string
  codigoMunicipal: string
  aliquotaISS: Aliquota          # por município do prestador
  exigibilidade: ExigibilidadeISS
  retencaoISS: bool
  aliquotaPis: Aliquota          # Lucro Presumido/Real
  aliquotaCofins: Aliquota
  retencaoPisCofins: TipoRetencaoPisCofins
  aliquotaIRRF: Aliquota
  retencaoIRRF: bool
  aliquotaCSLL: Aliquota
  retencaoCSLL: bool
  retencaoCP: bool               # INSS tomador
  cstIbsCbs: string
  cClassTrib: string
  cIndOp: string

ProdutoFiscalConfig:
  ncm: string                    # 8 dígitos
  cest: string?
  cfop: CodigoCfop
  cst: string                    # CST ou CSOSN
  aliquotaIcms: Aliquota
  reducaoBcIcms: Aliquota
  aliquotaFcp: Aliquota
  temST: bool
  mvaAjustado: Aliquota?
  aliquotaIcmsSt: Aliquota?
  cstPis: string
  aliquotaPis: Aliquota
  cstCofins: string
  aliquotaCofins: Aliquota
  temIpi: bool
  cstIpi: string?
  aliquotaIpi: Aliquota?
  cstIbsCbs: string
  cClassTrib: string
  cIndOp: string
```

---

## 16. Cronograma de Obrigatoriedades (referência para a lib)

| Data | Evento | Impacta |
|------|--------|---------|
| 01/08/2026 | Grupo IBSCBS obrigatório na NFS-e | NFS-e Regime Normal |
| 03/08/2026 | IBS/CBS obrigatório em NF-e/NFC-e produção (Regime Normal, CRT=3) | NF-e/NFC-e Regime Normal |
| 05/10/2026 | Devolução referencia obrigatoriamente DFeReferenciado | NF-e devolução |
| Jul/2026 | CNPJ alfanumérico em todos os DF-e | NF-e, NFC-e, NFS-e |
| 04/01/2027 | IBS/CBS obrigatório NF-e/NFC-e (Simples Nacional, CRT=1/2) | NF-e/NFC-e SN |

**A lib deve verificar datas em build() e emitir `ValidationException` se o grupo IBS/CBS estiver ausente após o prazo correspondente ao regime do emitente.**

---

## 17. Testes Obrigatórios por Módulo

### 17.1 TaxEngine
- Calcular ICMS para cada CST/CSOSN relevante (00,10,20,30,40,41,50,51,60,70,90 e CSOSN 101-900)
- DIFAL interestadual consumidor final — verificar partilha correta
- FCP + FCP ST
- ICMS ST com MVA ajustado
- IPI tributado vs. isento vs. NT
- PIS/COFINS Lucro Presumido vs. Lucro Real vs. Simples Nacional
- IBS/CBS "por fora" não embutido no preço
- ISS retido vs. não retido, Simples vs. Regime Normal
- Arredondamento bancário — testar casos extremos (0,005)
- Zona Franca Manaus → CBS zero

### 17.2 Builders
- build() com todos os campos obrigatórios → sucesso
- build() sem campo obrigatório → MissingFieldException
- Totais com 3 itens diferentes → vNF bate com somatório
- NFC-e sem destinatário acima de R$ 10.000 → ValidationException
- NF-e com CFOP 7xxx sem grupo exporta → ValidationException
- NFS-e sem IBSCBS após 01/08/2026 → ValidationException
- CNPJ alfanumérico válido após jul/2026

### 17.3 Serialização
- JSON de request tem todos os campos esperados pela API .NET
- Deserialização de response autorizada → NfResponse correto
- Deserialização de response com erro → RejectionException com lista de erros

### 17.4 Integração
- Emissão em homologação com resposta real da API .NET
- Cancelamento com justificativa mínima e máxima
- Substituição NFS-e com motivo 07

---

## 18. Versionamento e Changelogs

A lib usa SemVer. Breaking changes somente em major. Notas Técnicas da SEFAZ que adicionam campos obrigatórios são **minor** (não quebram integração existente enquanto toleradas, viram **major** quando a rejeição técnica é ativada).

```
CHANGELOG.md (estrutura mínima por entrada):
  ## [x.y.z] - AAAA-MM-DD
  ### Base normativa
  - NT 2025.002 v1.40 / NT 009/2026 / etc.
  ### Adicionado
  ### Alterado
  ### Deprecated
  ### Removido
  ### Corrigido
```

---

*Spec gerada com base em: Manual de Integração NFS-e Nacional v1.01, NT SE/CGNFS-e 003/2025, 004 v2.00, 007/2026, 008/2026, 009/2026; NT NF-e 2025.002-RTC v1.40; Ajuste SINIEF 12/2026; LC 214/2025; Lei Complementar 116/2003.*
