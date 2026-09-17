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
- Guia de integração: seção 9.6 "Octane / FrankenPHP (worker mode)" — o que é seguro,
  proibição de polling bloqueante em request HTTP, caveat do singleton capturando
  config por worker e receita multi-tenant.

### Alterado
- `FiscalLib::gestao()` agora memoiza a instância `GestaoFiscalApi` (como os demais
  serviços), reutilizando o Guzzle client em vez de criar um novo a cada chamada.
- Docblock do `FiscalLibServiceProvider` documenta o comportamento do binding singleton
  em Laravel Octane/FrankenPHP (config capturada na primeira resolução de cada worker).
