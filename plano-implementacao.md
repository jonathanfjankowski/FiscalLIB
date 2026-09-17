# FiscalLIB — Plano de Implementação (Pacote PHP)

> **Status:** implementado — v0.1.0 lançada (2026-09-07); E2E contra FiscalAPI
> + SEFAZ-PR homologação real validado (ver `CHANGELOG.md`). Este documento é
> o plano original, preservado como referência histórica.
> **Data:** 2026-09-06
> **Fontes:** `spec-lib-fiscal.md` (raiz), FiscalAPI real (`C:\Projetos\Pessoal\FiscalAPI`) — `docs/integracao-api.md` e `docs/plano-evolucao-contrato-v2.md`.

---

## 1. Decisões fechadas

- **Arquitetura (hexagonal)**: a lib é o **núcleo** — motor tributário, regras de negócio, validações e o **modelo do documento fiscal**. Ela define a porta `EmissorInterface`; **não implementa** emissão SEFAZ direta nem outras APIs.
- **Adaptador incluso**: só um — `Adapters/FiscalApi/` (consome a FiscalAPI real: `/v1/documentos-fiscais/*`, `impostosV2`, `Authorization: ApiKey`, `Idempotency-Key`, 202 assíncrono + polling, RFC 7807).
- **Extensibilidade**: quem quiser outra API ou SEFAZ direta implementa `EmissorInterface` no próprio código/pacote, consumindo o modelo tipado que a lib produz. O contrato de extensão será documentado.
- **Contrato**: alinhado à **API real** — as seções 1.3 e 8 da spec (contrato `/fiscal/*/emitir`, `NfResponse` síncrono) serão atualizadas.
- **Stack**: PHP 8.1+, Composer; primeiro consumidor é o ERP Laravel. Pastas já preparadas para o pacote C#/NuGet futuro (monorepo da spec §2).
- **Cobertura**: núcleo fiscal + todos os endpoints de gestão da FiscalAPI (certificados, api-keys, tenants, status-serviço, notas recebidas + manifestação, webhooks HMAC).
- **Premissa**: a FiscalAPI não calcula tributos — a lib calcula e envia valores prontos; a API valida aritmética (tolerância R$ 0,01).

---

## 2. Fluxo da lib

```
ERP
 │  dados do pedido/produto/serviço (ProdutoFiscalConfig, ServicoFiscalConfig — spec §15)
 ▼
TaxEngine (puro) ──► resultados tributários no formato dos grupos impostosV2 / DPS
 ▼
Builders Nfe|Nfce|Nfse (validações R001–R016, NFC-e, NFS-e) ──► Documento/ (modelo intermediário tipado)
 ▼
EmissorInterface  ◄── porta (Contracts/)
 ├─► Adapters/FiscalApi  (embutido: modelo → JSON /v1 → 202 → polling → resultado consolidado)
 └─► implementação própria do integrador (outra API / SEFAZ direta) — fora desta lib
```

`$lib->nfe()->emitir($payload)` delega ao emissor configurado (default: `EmissorFiscalApi`). Alto nível (espera estado terminal) e baixo nível (`emitirAsync()` devolve o `id` do 202 para o ERP persistir antes de qualquer coisa).

---

## 3. Estrutura

```
FiscalLIB/
├── composer.json          # jonathanfjankowski/fiscal-lib · PHP ^8.1 · guzzlehttp/guzzle ^7.8 · phpunit ^11 · phpstan
├── src/
│   ├── FiscalLib.php      # entrypoint: ->nfe(), ->nfce(), ->nfse(), ->eventos(), ->gestao()
│   ├── Config/FiscalConfig.php
│   ├── Contracts/         # EmissorInterface (porta), TaxEngineInterface, DTOs de resultado genéricos
│   ├── Documento/         # MODELO intermediário tipado (lingua franca): NfeModel, ItemModel,
│   │                      #   TributosModel (icms/st/difal/ipi/pis/cofins/ibsCbs/is), NfseModel,
│   │                      #   ResultadoEmissao, ResultadoEvento, StatusDocumento
│   ├── Tax/               # TaxEngine + contexts/results + ArredondadorBancario (bcmath, round half to even)
│   ├── Nfe/ Nfce/ Nfse/   # builders: ERP → Documento/* (validações de regra antes de emitir)
│   ├── Adapters/FiscalApi/# EmissorFiscalApi (implementa EmissorInterface) + Http client + Idempotencia
│   │                      #   + AguardadorPolling + ProblemDetailsParser + recursos (Emissao, Eventos,
│   │                      #   Certificados, ApiKeys, Tenants, NotasRecebidas, StatusServico)
│   ├── Webhook/           # VerificadorAssinaturaHmac (específico da FiscalAPI)
│   ├── Common/            # Enums/ (com mapeamento p/ strings da API) e ValueObjects/
│   └── Exceptions/
├── tests/                 # Unit · Contract (golden JSONs) · Integration
├── docs/                  # fiscal-rules.md · payload-contract.md (inclui "como implementar seu Emissor")
├── examples/
└── CHANGELOG.md
```

---

## 4. Módulos

### 4.1 `Common/`

- **Enums mapeando valores da API**: `Ambiente` (`producao`/`homologacao`), `ModeloDocumento` (55/65), `TipoOperacao` (`saida`/`entrada`), `Finalidade` (`normal`/`complementar`/`ajuste`/`devolucao`), `IndicadorPresenca` (`presencial`/`internet`/`teleatendimento`/`entrega_domicilio`/`fora_estabelecimento`/`outros`), `ConsumidorFinal` (`sim`/`nao`), `RegimeTributario`, `StatusDocumento` com `isTerminal()` (terminais: AUTORIZADA, REJEITADA, DENEGADA, CANCELADA, ERRO_INTERNO; transitórios: PENDENTE, PROCESSANDO, CONTINGENCIA, CANCELAMENTO_PENDENTE).
- **Value Objects com validação ativa**: `Cnpj` (DV; preparado p/ alfanumérico NT 009/2026), `Cpf` (DV), `CepBr`, `CodigoMunibge` (7 díg.), `ChaveAcesso` (44/50 posições + DV módulo 11), `CodigoNbs` (9 díg.), `CodigoCfop` (4 díg.), `Aliquota`, `ValorMonetario`.
- **ArredondadorBancario** (round half to even) sobre bcmath/strings — nenhum cálculo com float.

### 4.2 `Tax/`

- `TaxEngineInterface::calcularNfe/Nfce/Nfse(ctx): result` — stateless, sem I/O.
- **ICMS**: CST `00/10/20/40/41/51/60/70/90` + CSOSN `101–900`; ST própria (MVA ajustado, redução BC-ST), FCP e FCP-ST, diferimento (CST 51), crédito SN (101/201/900). CST `30` e `ICMSPart` não são suportados pela API → `TaxInconsistencyException` (fail-loud).
- **DIFAL**: regra vigente do Convênio 190/2017 — partilha **100% destino** (`valorIcmsOrigem = 0`), alíquota interestadual 4/7/12. *(A spec §4.3 traz a fórmula da partilha antiga 2016–2018 — será corrigida.)*
- **IPI** (trio + `cEnq` default `999`), **PIS/COFINS** (CSTs `01/02/04–09/99`; `03` por quantidade fora do escopo da API).
- **IBS/CBS** (LC 214/2025): `cstIbsCbs` + `cClassTrib`, IBS estadual/municipal, CBS, diferimentos, crédito presumido; **IS** por valor ou quantidade (unidade+quantidade sempre juntos). "Por fora": soma no total da nota.
- **NFS-e**: ISS (alíquota, `tributacaoIssqn`/`retencaoIssqn`, exigibilidade suspensa c/ processo), federais com a regra NT 007/2026 (`vPis`/`vCofins` = devidos, nunca retidos; `vRetCP/vRetIRRF/vRetCSLL`), IBS/CBS na DPS = **só CST + cClassTrib** (valores calculados pelo ADN).
- Os results já saem no formato dos grupos `impostosV2` / bloco `valores` da DPS — o builder não re-mapeia.

### 4.3 Builders → `Documento/`

- `NfeRequestBuilder`/`NfceRequestBuilder` → `NfeModel`: identificação, destinatário+endereço, itens (`codigo`, `descricao`, `ncm`, `cest`, `cfop`, `gtin` default `"SEM GTIN"`, `unidade` default `"UN"`, `quantidade`, `valorUnitario`, `valorDesconto`, `valorTotal`, tributos), totais, `pagamento[]`, `naturezaOperacao`, `finalidade`, `tipoOperacao`, `indicadorPresenca`, `indicadorConsumidorFinal`, `nfesReferenciadas[]`.
- `NfseDpsBuilder` → `NfseModel` (`serie`, `dataCompetencia`, `tomador`+endereço, `servico{codigoTributarioNacional, descricaoServico, codigoNbs}`, `valores{valorServicos, tributacaoIssqn, retencaoIssqn, aliquotaIssqn}`, retenções federais, `ibscbs`, `informacoesComplementares`).
- **Validações pré-emissão** (a API não valida): DV de CNPJ/CPF/chave, `valorTotal = qtd × unitário`, aritmética por grupo (`base × alíq/100 = valor`, tol. 0,01), devolução exige `nfesReferenciadas`, NFC-e sem destinatário vNF ≤ 10.000, justificativas 15–1000 chars, CSTs isentos com valor 0 (CST 40/41/50/60, CSOSN 300/400).
- **`valorNota` pela fórmula v2 da API**: Σ brutos − descontos + frete + seguro + outras + ST + FCP + IPI + IS. *(A spec §5.1 soma vPIS+vCOFINS no vNF — prevalece a API, que rejeita com 422.)*
- O `EmissorFiscalApi` serializa o modelo → `EmissaoRequest`/`NfseDpsRequest` camelCase. **Outro emissor recebe o mesmo modelo tipado** e faz o que quiser (outro JSON, XML etc.).

### 4.4 `Adapters/FiscalApi/`

- PSR-18 com Guzzle; headers `Authorization: ApiKey <chave>` e opcional `X-Fiscal-Ambiente`.
- **Idempotência**: ERP fornece a key (recomendado: persistida no pedido/faturamento); fallback UUID v4 gerado pela lib. Retry de rede (timeout/5xx/429) **reutiliza a mesma key**.
- **Recursos**:
  - `EmissaoApi`: `emitirNfe/Nfce` (→202 `EmissaoAceitaResponse{id,status,links}`), `consultar(id)` (→`EmissaoResponse`), `baixarPdf(id, binario|base64)`, `emitirNfseDps`, `substituirNfse(id, dps, cMotivo 1–5|99, xMotivo)`.
  - `EventoApi`: `cancelar(id, justificativa 15–1000)`, `cartaCorrecao(id, correcao)` (só mod 55), `inutilizar(ambiente, modelo, serie, faixa, justificativa)`, `consultarInutilizacao(eventoId)`.
  - `CertificadoApi`: `upload(pfx, senha)` multipart, `listar()` (monitorar `validoAte`).
  - `ApiKeyApi`: `criar`, `listar`, `revogar`.
  - `TenantApi`: perfil fiscal (IE, IM, endereço, CSC da NFC-e) e webhooks (`webhookUrl`, `webhookSecret`).
  - `StatusServicoApi`: `consultar(modelo, ambiente)`.
  - `NotasRecebidasApi`: listar/detalhar/xmlCompleto + `manifestar(tipo 210200–210240, justificativa)`.
- **`AguardadorPolling`**: consulta até estado terminal — 1ª espera ~2s, backoff 2→5→10→20s, teto 60s, timeout total configurável; trata CONTINGENCIA/CANCELAMENTO_PENDENTE como transitórios.
- **Erros RFC 7807 → hierarquia** `FiscalLibException`: `ValidacaoException` (400/422, com dicionário `errors` e extensão `campo`), `AutenticacaoException` (401/403), `NaoEncontradoException` (404), `EstadoInvalidoException` (409), `LimiteRequisicoesException` (429), `ApiIndisponivelException` (5xx/timeout), `RejeicaoSefazException` (status REJEITADA/DENEGADA — carrega `motivoStatus` + `xmlRetornoSefaz` + extração do código de rejeição).

### 4.5 `Webhook/` e `Config`

- `VerificadorAssinaturaHmac`: valida `X-Fiscal-Signature: sha256=HMAC(secret, "{timestamp}.{body}")` com janela anti-replay de 5 min — o ERP usa para aceitar entregas de webhook com segurança.
- `FiscalConfig`: `baseUrl`, `apiKey`, `ambiente`, `timeoutHttp`, `intervalosPolling`, `timeoutTotalPolling`, `versaoLib` (vai no User-Agent). Injeção do emissor: `new FiscalLib($config, new EmissorFiscalApi(...))` — trocar o emissor é trocar o argumento.

---

## 5. Desvios da spec a registrar (spec + docs atualizados)

| Spec diz | Lib implementará (API real / lei vigente) |
|---|---|
| §1.3/§8: `/fiscal/*/emitir`, `NfResponse` síncrono | `/v1/documentos-fiscais/*`, 202 + polling, `EmissaoResponse` |
| §4.3 DIFAL com partilha antiga | Partilha 100% destino (Convênio 190/2017), alíq. inter. 4/7/12 |
| §5.1 vNF soma vPIS+vCOFINS | Fórmula v2 da API (sem PIS/COFINS; ST+FCP+IPI+IS incluídos) |
| R012: ERP controla numeração | API reserva número por (tenant, modelo, série, ambiente); inutilização limpa faixas |
| serie 0–999; cMotivo 01–09 | serie 1–999; cMotivo 1–5 e 99 |
| ambiente int 1/2 | string `producao`/`homologacao` (chave fk_live_/fk_test_ amarra o ambiente) |
| NFC-e QR/CSC no payload | CSC vai no perfil do tenant (`TenantApi`), não no request |

---

## 6. Fases de implementação

1. **Scaffold**: composer.json, PSR-4, PHPUnit, PHPStan, esqueleto de pastas, CI.
2. **Common**: VOs, enums, arredondador bancário, hierarquia de exceções.
3. **Documento/** (modelo tipado) + **Tax engine** + matriz de testes.
4. **Builders** NF-e/NFC-e + fixtures de contrato (golden JSONs espelhando `integracao-api.md`).
5. **Adapter FiscalApi core**: emissão, consulta, polling, eventos, PDF, parse RFC 7807.
6. **NFS-e**: builder + DPS + substituição.
7. **Gestão**: certificados, api-keys, tenants, status-serviço, notas recebidas + manifestação, webhook HMAC.
8. **Integração Laravel**: ServiceProvider + `config/fiscal-lib.php` + facade (dependência `illuminate` opcional/suggest).
9. **Docs**: `docs/fiscal-rules.md`, `docs/payload-contract.md` (com seção "implementando seu próprio Emissor"), README, CHANGELOG, atualização da `spec-lib-fiscal.md`.
10. **E2E** contra FiscalAPI em `ModoSandbox=true` (Docker): autorizada, rejeitada, contingência, cancelamento, CC-e, inutilização, DPS.

---

## 7. Testes

- **Unit**: matriz completa do TaxEngine (CST 00–90, CSOSN 101–900, DIFAL, FCP, IBS/CBS, IS, ISS retido, arredondamento 0,005); VOs (DV de CNPJ/CPF/chave 44/50); builders (campos obrigatórios, NFC-e >10k sem destinatário, devolução sem NF-ref → exceção).
- **Contract**: golden JSONs por cenário (CST 10 com ST, CSOSN 102, DIFAL, IBS/CBS, NFS-e retida) + fixtures de ProblemDetails 400/409/422.
- **Integration**: contra o sandbox da API real rodando em Docker (`ModoSandbox=true`, EmissorMock), cobrindo a máquina de estados inteira.
- **Porta**: um `EmissorFake` nos testes prova que o núcleo funciona sem o adaptador (garantia da extensibilidade).

---

## 8. Entregáveis

- Pacote Composer publicável: núcleo (cálculos, regras, modelo) + adaptador FiscalAPI.
- `docs/fiscal-rules.md` (regras do motor tributário) + `docs/payload-contract.md` (mapeamento ERP → builder → modelo → JSON, e contrato para implementar outros emissores).
- `spec-lib-fiscal.md` atualizada (seções 1.3, 8 e correções da tabela §5).
- `examples/` com fluxos do dia a dia do ERP.
