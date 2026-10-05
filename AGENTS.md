# FiscalLIB — instruções para agentes

Biblioteca fiscal PHP (PHP ^8.1): motor tributário puro + builders NF-e/NFC-e/NFS-e Nacional + porta `EmissorInterface` com adaptador FiscalAPI. Primeiro consumidor: ERP Laravel.

## Comandos

```bash
composer test                # Unit + Contract + Port (rápido, offline — deve estar sempre verde)
composer test:integration    # E2E contra FiscalAPI real; auto-pula sem API no ar + ADMIN_PASSWORD
composer stan                # PHPStan nível 5 — deve estar limpo
php vendor/bin/phpunit --testsuite Integration   # idem composer test:integration
```

## Arquitetura (hexagonal — respeitar as fronteiras)

- `src/Tax/TaxEngine` — cálculo tributário **puro**, sem I/O. Contextos em `src/Tax/Contextos/`, resultados em `src/Tax/Resultados/` (serializam direto para o contrato `impostosV2`/DPS da API — o builder não remapeia).
- `src/Nfe|Nfce|Nfse/` — builders com validações pré-emissão (regras R0xx). Saída: modelo tipado em `src/Documento/` (língua franca entre builders e emissores).
- `src/Contracts/EmissorInterface` — porta; o núcleo não conhece HTTP. Só `src/Adapters/FiscalApi/` fala com a API.
- `src/Laravel/` é opcional (`suggest`), excluído do PHPStan.

## Regras de código

- `declare(strict_types=1)` em todo arquivo; propriedades `readonly` nos DTOs/VOs.
- **Zero float em cálculo fiscal**: bcmath via `Common\Matematica` + arredondamento bancário (`Common\ArredondadorBancario`, half-to-even). Valor = base × alíquota / 100, tolerância 0,01.
- Nomenclatura em PT-BR (domínio fiscal). Falha alta: CST/CSOSN fora do contrato → `TaxInconsistencyException`, nunca silenciar.
- Percentuais com 4 casas, valores monetários com 2.

## Contrato com a FiscalAPI (repositório irmão)

- **`C:\Projetos\Pessoal\FiscalAPI`** (C#/.NET) é a fonte da verdade da aritmética (`ValidadorImpostosV2`) e do payload (ver `docs/integracao-api.md` **no repositório irmão** — este arquivo não existe aqui). Qualquer fórmula nova/alterada aqui **deve ser espelhada lá** (e vice-versa), incluindo testes nos dois lados.
- Exemplos de pares corrigidos juntos: DIFAL 815/816, `vIBS` 1150, `indIntermed` 434, `IdDest` 521.
- Aritmética fiscal: ver `docs/fiscal-rules.md` **antes** de mexer em `src/Tax/`. Mapeamento ERP→payload: `docs/payload-contract.md`.

## E2E contra SEFAZ-PR real (gotchas)

- Stack Docker da FiscalAPI: `cd ../FiscalAPI/docker && MODO_SANDBOX=false docker compose --env-file ../.env up -d`. **Atenção**: o `.env` da raiz tem `MODO_SANDBOX=true` — sempre sobrescrever para false (modo real/homologação).
- `ADMIN_PASSWORD` está no `.env` da FiscalAPI. Tenant E2E usa o CNPJ do certificado (override: `FISCAL_TENANT_CNPJ`); CNPJ-base ≠ certificado → rejeição 213.
- Tenant é Inova Simples (forma jurídica) **não optante do Simples Nacional** → regime normal: payloads E2E usam CST (não CSOSN). Se a empresa vier a optar pelo Simples, reverter NFC-e para CSOSN.
- `tests/E2E/*.php` são scripts manuais de diagnóstico (bootstrap-cert, set-perfil, diag-*) — fora do PHPStan e da suíte; não viram testes.
- Rejeições SEFAZ já diagnosticadas ficam registradas no `CHANGELOG.md` (ambos os repositórios) — consultá-las antes de investigar do zero.
- Em scripts PHP, FQDN inline `new FiscalLib\X` pode resolver errado; sempre usar `use` + nome curto.
