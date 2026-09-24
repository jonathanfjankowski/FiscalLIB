# CHANGELOG

Todas as mudanças notáveis seguem [SemVer](https://semver.org). Notas Técnicas da
SEFAZ que adicionam campos obrigatórios são **minor** enquanto toleradas e **major**
quando a rejeição técnica é ativada.

## [Unreleased]

### Corrigido
- **`ResolvedorAliquotas::aliquotasIbsCbs(2026)`** — fase-teste 2026 passa a
  embutir IBS UF 0,10 / IBS Mun 0,00 (0,1% integral na UF). A divisão 0,05/0,05
  era rejeitada pela SEFAZ homologação com **1026** (alíquota do IBS da UF
  inválida); CBS 0,9% / IBS 0,1% com UF 0,10 / Mun 0,00 foi aceita — mesmas
  alíquotas que o E2E já usa explícitas. Docs (`fiscal-rules.md`,
  `integracao-erp-laravel.md`, `tabelas-aliquotas-fiscallib.md` §11/§14) e
  teste unitário alinhados.
- **Arredondamento bancário agora vale para TODA a aritmética** —
  `Matematica::percentualDe/multiplicar/dividir/somar/subtrair` truncavam via
  bcmath e podiam subavaliar até 1 centavo por cálculo (base 2 casas × alíquota
  4 casas). Agora calculam com dígitos de guarda e arredondam half-to-even
  (V004) uma única vez, conforme `docs/fiscal-rules.md`. ⚠️ **Espelhar na
  FiscalAPI (`ValidadorImpostosV2`)** — a tolerância 0,01 mascarava a
  divergência.
- **`totalTributos` da NFS-e ia como string JSON** — as chaves aninhadas
  (`federal`/`estadual`/`municipal`) não casavam com o regex de numeração do
  adaptador; agora saem como números (golden test cobre o campo).
- **Percentuais ecoados normalizados em 4 casas** — pCredSN (CSOSN 101/201/900),
  FCP-ST, FCP-ST retido, FCP do DIFAL e alíquotas PIS/COFINS da NFS-e (o
  contrato trata percentuais com 4 casas; havia dois formatos no mesmo payload).
- **CST 51** — a decisão de diferimento compara pDif em 4 casas (antes 2:
  diferimento < 0,005 era tratado como zero e a aritmética não fechava).
- `Matematica::normalizar()` rejeita `'1e3'`, `'.5'` e `'+5'` com
  `InvalidArgumentException` (passavam em `is_numeric` e quebravam o bcmath
  com `ValueError` genérico downstream).
- `MapeadorResultado` lança `SerializationException` se a resposta vier sem
  `id`/`eventoId` — antes produzia id vazio que o ERP persistia e a
  consulta/cancelamento quebrava depois (404).
- Upload de certificado A1 envia `Idempotency-Key` — o retry de 429/5xx não
  duplica mais o reenvio de PFX+senha.
- `AguardadorTerminal` valida `intervalosPolling` não-vazio (config `[]`
  gerava índice -1 + `sleep(null)`).
- R-NFS006 usa a data local de Brasília como pivô (`gmdate`/UTC avançava o dia
  às 21h locais, exigindo/omitindo o bloco IBS/CBS um dia errado).
- Docblocks de `IbsCbsEntrada`/`IbsCbsResultado` alinhados: IBS/CBS NÃO compõem
  o valorNota.

### Adicionado
- **Validações de faixa no TaxEngine** (falha alta na entrada, nunca valor
  negativo silencioso): alíquotas/percentuais fora de 0–100 (MVA: só
  não-negativo), desconto maior que o bruto, valores negativos em geral e
  DIFAL com alíquota interna < interestadual → `TaxInconsistencyException`.
- **Regras R0xx novas nos builders**: xProd ≤ 120 (R015), natOp ≤ 60, cProd ≤
  60, uCom ≤ 6, infCpl ≤ 5000, NCM 8 dígitos, CEST 7 dígitos, GTIN 8/12/13/14,
  quantidade/valorUnitario > 0, NF-e 55 exige destinatário e NFC-e bloqueia
  série 900–999 (R-NFC003 — reservada a contingência offline).
- **Regras novas no engine**: CST 10 com redução → exceção (use CST 70);
  `percentualCreditoSimples` em CST/CSOSN que não admitem pCredSN → exceção;
  flag `difal` em CST/CSOSN inadmissível → exceção (era descartada em
  silêncio); NFS-e valida domínios (tributacaoIssqn 1–4, retencaoIssqn 1–3,
  tipoRetencaoPisCofins 1–3, cstPisCofins na tabela do contrato) e exige
  alíquota ISS em operação tributável.
- **Cronograma IBS/CBS em `build()` de NF-e/NFC-e** (docs/fiscal-rules.md
  §Cronograma, spec §17): item com ICMS sem grupo IBS/CBS falha a partir de
  03/08/2026 (regime normal — CST) e 04/01/2027 (Simples Nacional — CSOSN),
  na data local de Brasília; regime identificado pelo código do item.
- **R-NFS014 (exportação de serviços)**: `NfseBuilder::codigoPaisResultado()`
  (cPaisResult, ISO 3166-1 numérico, 3 dígitos) — obrigatório com
  `tributacaoIssqn = 3`, que passa a exigir alíquota ISS nula/zero; campo
  `codigoPaisResultado` serializado em `valores`. **Espelhado na FiscalAPI**
  (`NfseValoresDto.CodigoPaisResultado` + validação no `ValidadorNfseDps` +
  mapeamento DPS).
- **Validações de evento na PORTA** (`ServicoEventos`): justificativa/
  correção 15–1000 caracteres e faixa de inutilização coerente valem para
  QUALQUER `EmissorInterface` (antes só no adaptador FiscalAPI).
- `MatematicaTest` — a classe de aritmética não tinha teste algum.
- `GestaoFiscalApiTest` — contrato HTTP dos endpoints de gestão (certificados,
  api-keys, perfil/webhooks, notas recebidas/manifestação), antes sem teste.
- Testes da integração Laravel (`tests/Laravel/`, suíte nova) — provider,
  singleton e alias; pulam sem illuminate instalado.
- Testes de porta para `substituir()` de NFS-e e para as validações de evento.
- Adaptador rejeita explicitamente `informacoesComplementares` na NF-e (a API
  não transmite infCpl — antes o texto era descartado em silêncio).

### Alterado
- NF-e aceita série 0 (spec 0–999; antes o builder exigia mínimo 1).
- `FiscalConfig::$versaoLib` → `0.2.1` (User-Agent estava congelado em 0.1.0).
- Docs alinhados ao código: `payload-contract.md` (intermediador com enum;
  referência de teste corrigida para `FiscalLibFluxoTest`), guia de integração
  §1.2–§1.4 e §6 (versão 0.2.x, remote do GitHub, assinatura do `pagamento`
  sem união com string), README (aviso de pacote não publicado no Packagist,
  contagem de testes sem número congelado), `fiscal-rules.md` (CST 50 marcado
  como fora do contrato).

## [0.2.0] — 2026-09-18

Release **breaking** (sem consumidor em produção). Contrato de payload e
aritmética do `TaxEngine` inalterados — nada a espelhar na FiscalAPI.

### Adicionado
- **`Tax\Tabelas\ResolvedorAliquotas`** — alíquotas determinísticas embutidas,
  puro (sem I/O), consultado pelo ERP antes de montar o contexto:
  - `aliquotaInterestadual(UF, UF, OrigemMercadoria)` — 4/7/12 por par de UFs ×
    origem (Res. Senado 22/1989 e 13/2012);
  - `parametrosDifal(UF, UF, orig)` — interestadual + interna geral + FCP do
    destino, casando 1:1 com `difalInterestadual()`;
  - `aliquotaInternaGeral(UF)` / `aliquotaFcp(UF)` — regra geral das 27 UFs e
    FCP/FECP adicional (fonte: `tabelas-aliquotas-fiscallib.md` §2/§4);
  - `aliquotasIbsCbs(2026)` — fase-teste da LC 214/2025 (outro ano falha alto);
  - overrides imutáveis `comAliquotaInterna()`/`comFcp()` — vencem a tabela para
    produtos com alíquota diferenciada.
- Enums `UF` (27), `IndicadorIntermediador` (NT 2020.006), `CstIcms`, `Csosn`,
  `CstIpi`, `CstPisCofins` (com helpers de grupo `tributado()`/`exigeAliquota()`).
- Testes: `ResolvedorAliquotasTest` (matriz interestadual e DIFAL contra a tabela
  de referência) e `EnumsFiscaisTest` (cases = fonte única do contrato).

### Quebrado (breaking)
- **CST/CSOSN agora são enums** — `NfeTaxContext::icms(OrigemMercadoria, CstIcms|Csosn|null, …)`,
  `st(ModoDeterminacaoBc, …)`, `ipi(CstIpi, …)`, `pis()/cofins()(CstPisCofins, …)`.
  Combinação fora do contrato não compila; as allow-lists do `TaxEngine`
  (`CSTS_SUPORTADOS` etc.) foram substituídas pelas cases dos enums (fonte única).
- `pagamento(FormaPagamento $forma, …)` — sem união com string.
- `intermediador(IndicadorIntermediador $indicador, ?string $cnpj = null)` — sem
  validação de int na mão (indIntermed 2 = erro de tipo).
- `Endereco::$uf` — `?UF` (era string livre).
- `Pagamento::$forma` — `FormaPagamento` (helper `formaCodigo()` mantido).

### Docs
- `tabelas-aliquotas-fiscallib.md` §14 reescrita — descreve o que o resolvedor
  realmente embute (era aspiracional).
- `fiscal-rules.md` ganhou a seção "Alíquotas embutidas"; guia de integração
  ganhou §5.6 com exemplos do resolvedor e overrides.

## [0.1.1] — 2026-09-17

### Corrigido
- **DIFAL: `valorIcmsDestino` era a interna cheia** — o motor calculava
  `base × aliquotaInternaUfDestino`, mas o MOC (rejeições SEFAZ **815/816**)
  define `vICMSUFDest = vBCUFDest × (pICMSUFDest − aliquotaInterestadual)`:
  o ICMS próprio (base × interestadual) já remete à UF de origem. Exemplo
  (BC 1000, inter 7%, interna 18%): 180,00 → **110,00**. Teste da matriz
  reescrito com cenário coerente (próprio = interestadual).

### Adicionado
- `NfeBuilder::intermediador(int $indicador, ?string $cnpj = null)` — suporte
  ao marketplace/intermediador (NT 2020.006, `indIntermed`, só NF-e 55; 0 =
  sem intermediador, 1 = plataforma de terceiros com CNPJ obrigatório). O
  adaptador serializa `indicadorIntermediador`/`cnpjIntermediador` no payload
  → grupo `infIntermed`. Sem o campo a SEFAZ-PR rejeita a NF-e com 434.
- 4 testes novos (builder + mapeamento do intermediador) — 88 no total.
- Guia de integração: seção 9.6 "Octane / FrankenPHP (worker mode)" — o que é seguro,
  proibição de polling bloqueante em request HTTP, caveat do singleton capturando
  config por worker e receita multi-tenant.

### Alterado
- **Suíte de integração (`SandboxE2eTest`) alinhada à homologação real
  SEFAZ-PR** (validada de ponta a ponta contra a FiscalAPI com cert A1):
  tenant usa o CNPJ do certificado (parametrizável via `FISCAL_TENANT_CNPJ`,
  a 213 exige CNPJ-base igual), endereços completos com `nomeMunicipio`,
  item com a descrição obrigatória de homologação + NCM real (778),
  destinatário PJ com IE válida (805), alíquota interestadual 12% + CFOP
  6102 (521/693), PIS/COFINS (745) e IBS/CBS com as alíquotas de teste 2026
  — IBS UF 0,1% / CBS 0,9% (1026). NF-e e NFC-e progridem até a rejeição
  230 (IE do emitente não cadastrada, pendência cadastral em resolução).
  NFC-e do E2E usa CST 00 (regime normal do tenant — OKTO é Inova Simples
  como forma jurídica, sem opt-in no Simples Nacional).
- `phpstan.neon` exclui `tests/E2E` (scripts manuais de diagnóstico, fora da
  suíte).
- Novos scripts de suporte em `tests/E2E/`: `bootstrap-cert.php` (tenant +
  api-key + certificado A1), `set-perfil.php` (perfil fiscal do emitente),
  `diag-emitir.php`/`diag-nfce.php`/`diag-modelos.php` (emissão dirigida com
  motivoStatus terminal).
- `FiscalLib::gestao()` agora memoiza a instância `GestaoFiscalApi` (como os demais
  serviços), reutilizando o Guzzle client em vez de criar um novo a cada chamada.
- Docblock do `FiscalLibServiceProvider` documenta o comportamento do binding singleton
  em Laravel Octane/FrankenPHP (config capturada na primeira resolução de cada worker).

## [0.1.0] - 2026-09-07

### Base normativa
- NT 2025.002-RTC v1.40 (NF-e/NFC-e — IBS/CBS/IS, fórmula do total v2)
- NT SE/CGNFS-e 004 v2.00 · 007/2026 · 009/2026 (NFS-e Nacional, DPS layout 1.01)
- LC 214/2025 · Ajuste SINIEF 12/2026 · Convênio 190/2017 (DIFAL)

### Adicionado
- Motor tributário puro (`Tax\TaxEngine`): ICMS CST 00/10/20/40/41/51/60/70/90,
  CSOSN 101–900, ST própria/retida, FCP/FCP-ST, diferimento, DIFAL (partilha 100%
  destino), IPI, PIS/COFINS, IBS/CBS e IS (LC 214/2025), ISS/retencões NFS-e.
- Arredondamento bancário (round half to even) sobre bcmath — zero float.
- Builders NF-e/NFC-e/NFS-e com validações pré-emissão (R001–R016, R-NFC001–008, R-NFS001–006).
- Modelo intermediário `Documento\` (lingua franca entre builders e emissores).
- Porta `Contracts\EmissorInterface` — núcleo independente de transporte.
- Adaptador FiscalAPI: `/v1/documentos-fiscais/*`, ApiKey, Idempotency-Key estável,
  polling até estado terminal, retry com a mesma key, RFC 7807 → exceções tipadas.
- Endpoints de gestão: certificados, api-keys, perfil/webhooks do tenant,
  status-serviço, notas recebidas + manifestação.
- Validador HMAC-SHA256 de webhooks (janela anti-replay 5 min).
- Integração Laravel opcional (ServiceProvider + Facade + config publicável).
- 84 testes (unit/contract/port) + PHPStan nível 5 limpo.
