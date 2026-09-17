# CHANGELOG

Todas as mudanças notáveis seguem [SemVer](https://semver.org). Notas Técnicas da
SEFAZ que adicionam campos obrigatórios são **minor** enquanto toleradas e **major**
quando a rejeição técnica é ativada.

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

## [Não lançada]

### Adicionado
- `NfeBuilder::intermediador(int $indicador, ?string $cnpj = null)` — suporte
  ao marketplace/intermediador (NT 2020.006, `indIntermed`, só NF-e 55; 0 =
  sem intermediador, 1 = plataforma de terceiros com CNPJ obrigatório). O
  adaptador serializa `indicadorIntermediador`/`cnpjIntermediador` no payload
  → grupo `infIntermed`. Sem o campo a SEFAZ-PR rejeita a NF-e com 434.
- 4 testes novos (builder + mapeamento do intermediador) — 88 no total.

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
- `phpstan.neon` exclui `tests/E2E` (scripts manuais de diagnóstico, fora da
  suíte).
- Novos scripts de suporte em `tests/E2E/`: `bootstrap-cert.php` (tenant +
  api-key + certificado A1), `set-perfil.php` (perfil fiscal do emitente),
  `diag-emitir.php`/`diag-nfce.php`/`diag-modelos.php` (emissão dirigida com
  motivoStatus terminal).

## [Não lançada] — docs e worker

### Adicionado
- Guia de integração: seção 9.6 "Octane / FrankenPHP (worker mode)" — o que é seguro,
  proibição de polling bloqueante em request HTTP, caveat do singleton capturando
  config por worker e receita multi-tenant.

### Alterado
- `FiscalLib::gestao()` agora memoiza a instância `GestaoFiscalApi` (como os demais
  serviços), reutilizando o Guzzle client em vez de criar um novo a cada chamada.
- Docblock do `FiscalLibServiceProvider` documenta o comportamento do binding singleton
  em Laravel Octane/FrankenPHP (config capturada na primeira resolução de cada worker).
