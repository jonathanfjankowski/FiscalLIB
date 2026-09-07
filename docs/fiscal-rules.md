# Regras fiscais implementadas (`Tax\TaxEngine`)

> Fonte da verdade da aritmética: `ValidadorImpostosV2` da FiscalAPI.
> Toda fórmula é `base × alíquota / 100` com **arredondamento bancário**
> (round half to even, V004) sobre **bcmath/strings** — tolerância R$ 0,01.

## Base de cálculo

```
basePropria     = valorBruto − descontoIncondicionado
baseComReducao  = basePropria × (1 − percentualReducaoBc / 100)     (CST 20/51/70/900)
```

## ICMS — Regime Normal (CST)

| CST | Regra | Observações |
|-----|-------|-------------|
| 00 | trio próprio (base, alíq, valor) + FCP | redução não admitida (use 20) |
| 10 | trio + **ST própria** | `BC_ST = base × (1+MVA) × (1−redBCST)`; `vICMSST = BC_ST × alíqST` (fórmula direta — sem subtrair ICMS próprio, como valida a API) |
| 20 | trio sobre base reduzida | `percentualReducaoBc` obrigatório |
| 40/41/50 | sem valores próprios | isenta/NT/suspensão |
| 51 | diferimento | `vICMSOp = base × alíq`; `vICMSDif = vICMSOp × pDif`; **`valor` só é enviado quando pDif = 0** (o validador da API exige valor = base×alíq) |
| 60 | ST **retida** | campos `st.baseCalculoStRetido/aliquotaStRetida/valorStRetido` (vBCSTRet/pST/vICMSSTRet); `valorStRetido` calculado se omitido |
| 70 | redução + ST própria | ST parte da **base reduzida** |
| 90 | livre | valida o que vier; combinações opcionais |

Fora do contrato (falham alto em `build()`/cálculo): CST 02/15/30/53/61, `ICMSPart`, `ICMSST`.

## ICMS — Simples Nacional (CSOSN)

| CSOSN | Regra |
|-------|-------|
| 101 | crédito SN: `vCredSN = base × pCred` (sem ICMS próprio) |
| 102/103 | sem valores |
| 201 | crédito SN (opcional) + ST própria |
| 202/203 | ST própria |
| 300/400 | sem valores (isenta/NT) |
| 500 | ST retida (como CST 60) |
| 900 | completo (trio/crédito/ST/redução opcionais) |

## FCP

- **FCP próprio**: `vFCP = baseCalculo × pFCP` (base = trio do ICMS próprio)
- **FCP-ST**: `vFCPST = BC_ST × pFCPST`
- **FCP-ST retido** (60/500): sobre `baseCalculoStRetido`
- **FCP UF destino (DIFAL)**: `vFCPUFDest = baseDestino × pFCPUFDest`

FCP próprio **não** compõe o total da nota; FCP-ST compõe.

## DIFAL — operação interestadual com consumidor final

**Convênio 190/2017 (vigente): partilha de 100% para o UF de destino.**

```
aliquotaInterestadual ∈ {4, 7, 12}
vBCUFDest    = base própria
pICMSUFDest  = alíquota interna do destino
vICMSUFDest  = vBCUFDest × pICMSUFDest
vICMSUFRemet = 0
```

> A spec original (§4.3) trazia a fórmula da partilha antiga (2016–2018) — corrigida aqui.

## IPI

| CSTs | Comportamento |
|------|---------------|
| 00, 49, 50, 99 | tributado — trio obrigatório (`cEnq` default `999`) |
| 01–05, 51 | não tributado — sem valor |
| outros | fora do contrato → `TaxInconsistencyException` |

## PIS / COFINS

| CSTs | Comportamento |
|------|---------------|
| 01, 02 | tributado — trio obrigatório |
| 03 | por quantidade — **não suportado** pelo contrato atual |
| 04–09 | isento/NT/suspensão/monofásico — sem valor |
| 99 | outras — trio quando alíquota informada |

**NT 007/2026**: `vPIS`/`vCOFINS` são valores **DEVIDOS**, nunca retidos. Retenções
vão em campos próprios (`vRetCP`, `vRetIRRF`, `vRetCSLL`, `tpRetPisCofins`).

## IBS / CBS (LC 214/2025 — NT 2025.002 v1.40)

```
vIBS_UF       = base × alíquotaIbsEstadual
vIBS_Mun      = base × alíquotaIbsMunicipal
vCBS          = base × alíquotaCbs
percentualReducao* aplica por componente antes da alíquota
```

- Obrigatório: `cstIbsCbs` (3 díg., SEPEC) + `cClassTrib` (6 díg.).
- Prazos: **03/08/2026** Regime Normal · **04/01/2027** Simples Nacional.
- "Por fora": os valores **não** entram no `valorNota` (conferência à parte nos totais).

## Imposto Seletivo (IS)

```
vIS = base × alíquota          (base = própria ou override p/ base por quantidade)
```

- `cstIs` (2 díg.) + `cClassTribIs` (6 díg.) obrigatórios.
- Por quantidade: `unidadeTributavel` + `quantidadeTributavel` sempre juntos.

## NFS-e Nacional (ISS e federais)

```
base          = vServ − descontoIncondicionado
vISS          = base × alíquotaISS     (somente tributacaoIssqn = 1; imunidade/exportação/NT → 0)
vPIS/vCOFINS  = base × alíquota        (devidos)
vISSRet       = vISS quando retencaoIssqn ∈ {2,3}
```

- Bloco **IBSCBS obrigatório desde 01/08/2026** (R-NFS006) — só códigos
  (`cst` + `cClassTrib`); **os valores são calculados pelo ADN** (R-NFS007).
- `cNBS` com 9 dígitos (R-NFS001); endereço do tomador completo quando informado.

## Total da nota (fórmula determinística do contrato v2)

A fórmula v2 **ativa** quando houver qualquer um: desconto (item ou documento),
frete, seguro, outras despesas ou IPI em item.

```
Ativa:  valorNota = Σ brutos − descontos + frete + seguro + outras
                  + Σ vICMSST + Σ vFCPST + Σ vIPI
Inativa: valorNota = Σ brutos
```

IBS/CBS e IS **não** entram no total (são conferidos à parte pela API).

## Cronograma verificado em `build()`

| Data | Regra |
|------|-------|
| 01/08/2026 | IBSCBS obrigatório na DPS (R-NFS006) |
| 03/08/2026 | IBS/CBS obrigatório NF-e/NFC-e Regime Normal |
| 04/01/2027 | IBS/CBS obrigatório Simples Nacional |
| Jul/2026 | CNPJ alfanumérico aceito (NT 009/2026 — `Cnpj` VO) |
