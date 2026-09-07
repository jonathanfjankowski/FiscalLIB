# Regras Fiscais Completas — FiscalLib
## NF-e · NFC-e · NFS-e Nacional

**Base normativa:** NT 2025.002-RTC v1.40 · NT 2023.001 · NT 2016.002 · NT 2015.003 · LC 214/2025 · LC 116/2003 · NT NFS-e 003/2025 · 004 v2.00 · 007/2026 · 008/2026 · 009/2026 · Ajuste SINIEF 12/2026  
**Referência:** até 05/09/2026  

---

# PARTE 1 — NF-e (Modelo 55)

---

## 1.1 ICMS — Regime Normal (CRT = 3)

### Tabela de CST ICMS — campos obrigatórios por código

| CST | Descrição | orig | modBC | pRedBC | vBC | pICMS | vICMS | modBCST | pMVAST | vBCST | pICMSST | vICMSST | vBCSTRet | vICMSSTRet | pST |
|-----|-----------|------|-------|--------|-----|-------|-------|---------|--------|-------|---------|---------|----------|------------|-----|
| 00 | Tributada integralmente | S | S | N | S | S | S | N | N | N | N | N | N | N | N |
| 10 | Tributada + ST | S | S | N | S | S | S | S | S | S | S | S | N | N | N |
| 20 | Com redução de BC | S | S | S | S | S | S | N | N | N | N | N | N | N | N |
| 30 | Isenta/NT + ST | S | N | N | N | N | N | S | S | S | S | S | N | N | N |
| 40 | Isenta | S | N | N | N | N | N | N | N | N | N | N | N | N | N |
| 41 | Não tributada | S | N | N | N | N | N | N | N | N | N | N | N | N | N |
| 50 | Suspensão | S | N | N | N | N | N | N | N | N | N | N | N | N | N |
| 51 | Diferimento | S | S | N | S | S | S | N | N | N | N | N | N | N | N |
| 60 | ICMS cobrado ant. por ST | S | N | N | N | N | N | N | N | N | N | N | S | S | S* |
| 70 | Trib. red. BC + ST | S | S | S | S | S | S | S | S | S | S | S | N | N | N |
| 90 | Outros | S | ? | ? | ? | ? | ? | ? | ? | ? | ? | ? | ? | ? | ? |

*pST obrigatório no CST 60 somente quando indFinal=1 (consumidor final)

---

### Fórmulas por CST

#### CST 00 — Tributada integralmente

```
BC_ICMS = (vProd - vDesc) + vFrete + vSeg + vOutro - vDescCondIncond
          [modalidade 3 = valor da operação — mais comum]

vICMS   = BC_ICMS × pICMS / 100
vFCP    = BC_ICMS × pFCP / 100       (se pFCP > 0)
vTotalItem = vProd + vICMS_por_fora  (ICMS já está "por dentro" no preço)
```

**Modalidades de BC (modBC):**
- 0 = Margem de Valor Agregado (%)
- 1 = Pauta (valor fixo por unidade)
- 2 = Preço tabelado máximo
- 3 = Valor da operação ← mais usado

---

#### CST 10 — Tributada com Substituição Tributária

```
# Operação própria (mesma lógica CST 00)
BC_ICMS  = vProd - vDesc + vFrete + vSeg + vOutro
vICMS    = BC_ICMS × pICMS / 100

# Substituição
BC_ST    = (BC_ICMS × (1 + pMVAST/100)) × (1 - pRedBCST/100)
vICMSST  = (BC_ST × pICMSST/100) - vICMS   ← ST é o COMPLEMENTO
vFCPST   = BC_ST × pFCPST/100

# MVA ajustado (operação interestadual — protocolo ICMS)
pMVA_Aj = ((1 + pMVA_orig/100) × (1 - pICMS_interno/100) / (1 - pICMS_inter/100)) - 1) × 100
```

**Importante:** `vICMSST` nunca pode ser negativo. Se o resultado for negativo, `vICMSST = 0`.

---

#### CST 20 — Com redução de base de cálculo

```
BC_ICMS = (vProd - vDesc + vFrete + vSeg + vOutro) × (1 - pRedBC/100)
vICMS   = BC_ICMS × pICMS / 100
vICMSDeson = (vProd - vDesc + vFrete + vSeg + vOutro - BC_ICMS) × pICMS / 100
             [valor desonerado, informativo]
```

---

#### CST 30 — Isenta/NT com ST

```
vICMS    = 0
BC_ICMS  = 0

BC_ST    = (vProd - vDesc + vFrete + vSeg + vOutro) × (1 + pMVAST/100)
           × (1 - pRedBCST/100)
vICMSST  = BC_ST × pICMSST/100
vFCPST   = BC_ST × pFCPST/100
```

---

#### CST 40, 41, 50 — Isenta / Não tributada / Suspensão

```
Todos os campos de valor = 0
Informar apenas: orig + CST
vICMSDeson pode ser informado para CST 40 (desoneração)
  se vICMSDeson > 0 → motDesICMS obrigatório (1-9)
```

**Códigos de motivo desoneração (motDesICMS):**

| Código | Motivo |
|--------|--------|
| 1 | Táxi |
| 2 | Deficiente Físico |
| 3 | Produtor Agropecuário |
| 4 | Frotista/Locadora |
| 5 | Diplomático/Consular |
| 6 | Utilitários e Motocicletas da Amazônia Ocidental e Áreas de Livre Comércio |
| 7 | SUFRAMA |
| 8 | Venda a Órgão Público |
| 9 | Outros |
| 10 | Deficiente Condutor |
| 11 | Deficiente Não Condutor |
| 16 | Olimpíadas/Paraolimpíadas |

---

#### CST 51 — Diferimento

```
BC_ICMS    = vProd - vDesc + vFrete + vSeg + vOutro
vICMSOp    = BC_ICMS × pICMS / 100       [imposto da operação]
vICMSDif   = vICMSOp × pDif / 100        [parcela diferida]
vICMS      = vICMSOp - vICMSDif           [parcela não diferida]

pDif: percentual diferido — definido em convênio ou legislação estadual
Quando diferimento total: pDif = 100%, vICMS = 0
```

---

#### CST 60 — ICMS cobrado anteriormente por ST

```
vICMS      = 0
vBCSTRet   = BC que serviu de base para a ST retida anteriormente
vICMSSTRet = valor da ST retida anteriormente
pST        = percentual ST suportado pelo consumidor (obrigatório se indFinal=1)

# Venda para consumidor final com ST anteriormente retida:
vICMSEfet  = BC_ICMS_efetivo × pICMSEfet / 100
vICMSDif   = vICMSSTRet - vICMSEfet   [diferença a restituir se negativo, complementar se positivo]
```

---

#### CST 70 — Tributada com redução de BC e ST

```
BC_ICMS  = (vProd - vDesc + ...) × (1 - pRedBC/100)
vICMS    = BC_ICMS × pICMS / 100

BC_ST    = (BC_ICMS × (1 + pMVAST/100)) × (1 - pRedBCST/100)
vICMSST  = (BC_ST × pICMSST / 100) - vICMS
```

---

### FCP — Fundo de Combate à Pobreza

```
Regras:
- pFCP máximo: 2% (EC 132/2023)
- vFCP    = BC_ICMS × pFCP / 100     [operação própria]
- vFCPST  = BC_ST   × pFCPST / 100  [substituição]
- vFCPSTRet = informativo — FCP já retido anteriormente
- Estados que cobram FCP: AL, AM, BA, CE, ES, GO, MA, MG, MT, MS, PA, PB, PE, PI, RJ, RN, RS, SC, SE, SP, TO

FCP vai no totalizador da NF-e: vFCP + vFCPST somam ao vNF
```

---

### DIFAL — Operações Interestaduais para Consumidor Final (EC 87/2015)

**Condições de aplicação (todas devem ser verdadeiras):**
```
1. tpNF = 1 (saída)
2. indFinal = 1 (consumidor final)
3. indIEDest = 9 (não contribuinte do ICMS)
4. UF emitente ≠ UF destinatário
5. idDest = 2 (interestadual)
```

**Fórmulas:**
```
vBCUFDest  = BC_ICMS (mesma base da operação)
pICMSInter = alíquota interestadual (4%, 7% ou 12% conforme UF destino e origem)
pICMSUFDest = alíquota interna da UF de destino

vICMSDif   = vBCUFDest × (pICMSUFDest - pICMSInter) / 100

# Partilha permanente (pós período de transição):
vICMSUFDest = vICMSDif × 100% → 100% para o estado destino
vICMSUFRem  = 0                → 0% para o estado remetente

# FCP DIFAL
vFCPUFDest = vBCUFDest × pFCPUFDest / 100
```

**Alíquotas interestaduais:**
| Origem | Destino | Alíquota |
|--------|---------|----------|
| Qualquer | N, NE, CO, ES, MG | 7% |
| Qualquer | SP, RJ, PR, SC, RS | 12% |
| Qualquer | Importado | 4% |

---

### ICMS Monofásico sobre Combustíveis (NT 2023.001)

```
Aplica para NCMs de combustíveis específicos (tabela publicada no portal NF-e)

Campos adicionais:
  cProdANP    Código produto ANP (9 dígitos)
  pGLP        Percentual GLP derivado do petróleo (se aplicável)
  pGNn        Percentual GN nacional
  pGNi        Percentual GN importado
  vPart       Valor de partida
  CODIF       Código de autorização SEFAZ
  qTemp       Quantidade faturada temperatura ambiente
  UFCons      UF de consumo

CST específicos para monofásico:
  02 = Tributação monofásica própria (sobre combustível)
  15 = Tributação monofásica por ST (sobre combustível)
  53 = Tributação monofásica com diferimento
  61 = ICMS cobrado ant. monofásico (substituído)

vICMSMonoReten: valor ICMS monofásico sujeito a retenção — entra no totalizador
```

---

## 1.2 ICMS — Simples Nacional (CRT = 1 e 2)

### Tabela CSOSN

| CSOSN | Descrição | Campos adicionais |
|-------|-----------|-------------------|
| 101 | Tributada com permissão de crédito | pCredSN, vCredICMSSN |
| 102 | Tributada sem permissão de crédito | nenhum |
| 103 | Isenção SN (faixa receita) | nenhum |
| 201 | Trib. ST com crédito | pCredSN, vCredICMSSN + campos ST |
| 202 | Trib. ST sem crédito (fora faixa) | campos ST |
| 203 | Isenção SN com ST | campos ST |
| 300 | Imune | nenhum |
| 400 | Não tributada SN | nenhum |
| 500 | ICMS cobrado ant. por ST ou antecipação | vBCSTRet, vICMSSTRet, pST |
| 900 | Outros (SN com tributação normal) | campos conforme situação |

**Crédito SN (CSOSN 101 e 201):**
```
pCredSN    = alíquota de crédito (fixada no regime SN por faixa de receita)
vCredICMSSN = vBC × pCredSN / 100
```

---

## 1.3 IPI

### Tabela CST IPI completa

| CST | Descrição | Tipo |
|-----|-----------|------|
| 00 | Entrada com recuperação de crédito | Entrada |
| 01 | Entrada tributada com alíquota zero | Entrada |
| 02 | Entrada isenta | Entrada |
| 03 | Entrada não tributada | Entrada |
| 04 | Entrada imune | Entrada |
| 05 | Entrada com suspensão | Entrada |
| 49 | Outras entradas | Entrada |
| 50 | Saída tributada | Saída |
| 51 | Saída tributada com alíquota zero | Saída |
| 52 | Saída isenta | Saída |
| 53 | Saída não tributada | Saída |
| 54 | Saída imune | Saída |
| 55 | Saída com suspensão | Saída |
| 99 | Outras saídas | Saída |

### Fórmulas IPI

```
# Base de cálculo
BC_IPI = vProd - vDesc + vFrete + vSeg + vOutro

# IPI ad valorem (alíquota percentual)
vIPI = BC_IPI × pIPI / 100

# IPI por unidade (ad rem)
vIPI = qUnid × vUnid

# IPI compõe base ICMS? Depende do CFOP:
  CFOP de industrialização → IPI NÃO compõe BC ICMS
  CFOP de comercialização → IPI COMPÕE BC ICMS
  Regra: se comprador não for contribuinte de IPI → IPI entra na BC ICMS

# Campos obrigatórios CST 50:
  cEnq   código de enquadramento legal (3 dígitos)
  BC_IPI ou qUnid + vUnid
  pIPI ou vIPI
```

**Campos de controle:**
```
cnpjProd   CNPJ do produtor (para encomenda)
cSelo      código do selo de controle (cigarros, bebidas)
qSelo      quantidade de selos
cEnq       código de enquadramento (tabela TIPI)
```

---

## 1.4 PIS e COFINS

### Tabela CST PIS/COFINS completa

| CST | Descrição | Regime | Base |
|-----|-----------|--------|------|
| 01 | Operação tributável — alíquota normal | Cumulativo e não-cumulativo | % sobre valor |
| 02 | Operação tributável — alíquota diferenciada | Não-cumulativo | % sobre valor |
| 03 | Operação tributável — quantidade | Não-cumulativo | por unidade |
| 04 | Operação com incidência monofásica | Ambos | — |
| 05 | Operação tributável por ST | Ambos | — |
| 06 | Alienação de direito creditório vincendo | Não-cumulativo | — |
| 07 | Isenta | Ambos | — |
| 08 | Sem incidência | Ambos | — |
| 09 | Com suspensão | Ambos | — |
| 49 | Outras saídas | Ambos | — |
| 50 | Operação com direito a crédito — compra p/ insumo | Não-cumulativo | % |
| 51 | Crédito presumido — agropecuária | Não-cumulativo | % |
| 52 | Crédito — devolução de vendas | Não-cumulativo | % |
| 53 | Crédito — energia elétrica | Não-cumulativo | % |
| 54 | Crédito — aluguéis (máquinas) | Não-cumulativo | % |
| 55 | Crédito — aluguéis (edificações) | Não-cumulativo | % |
| 56 | Crédito — bens incorporados ao ativo imobilizado | Não-cumulativo | % |
| 60 | Crédito — devolução de compras | Não-cumulativo | % |
| 61 | Crédito — ativo imobilizado — depreciação | Não-cumulativo | % |
| 62 | Crédito — contraprestações de arrendamento mercantil | Não-cumulativo | % |
| 63 | Crédito — fretes na venda | Não-cumulativo | % |
| 64 | Crédito — vale-transporte, refeição e fardamento | Não-cumulativo | % |
| 65 | Crédito — armazenagem de mercadoria | Não-cumulativo | % |
| 66 | Crédito — outras operações | Não-cumulativo | % |
| 70 | Operações vinculadas a exportação | Não-cumulativo | — |
| 71–75 | Subtipos exportação | Não-cumulativo | — |
| 98 | Outras entradas | Ambos | — |
| 99 | Outras operações | Ambos | — |

### Alíquotas padrão

| Regime | PIS | COFINS |
|--------|-----|--------|
| Lucro Presumido (cumulativo) | 0,65% | 3,00% |
| Lucro Real (não-cumulativo) | 1,65% | 7,60% |
| Simples Nacional | 0% (CST 07) | 0% (CST 07) |
| Monofásico (combustível) | varia por NCM | varia por NCM |
| Substituição PIS/COFINS | 0% + destaque ST | 0% + destaque ST |

### Fórmulas

```
# Ad valorem (CST 01 e 02)
BC_PIS    = vProd - vDesc
BC_COFINS = vProd - vDesc
vPIS    = BC_PIS    × pPIS / 100
vCOFINS = BC_COFINS × pCOFINS / 100

# Por quantidade (CST 03)
vPIS    = qBCProd × vAliqProd_PIS
vCOFINS = qBCProd × vAliqProd_COFINS

# Regra NT 007/2026 (NFS-e, mas mesma lógica para NF-e quando há retenção):
vPIS    = valor DEVIDO na operação (não o retido)
vCOFINS = valor DEVIDO na operação (não o retido)
```

### PIS-ST e COFINS-ST

```
Aplicam quando há substituição tributária de PIS/COFINS
Campos: vBC_PISST, pPISST, qBCProdST, vAliqProdST, vPISST
         idem para COFINS-ST
Integram o totalizador da nota separadamente
```

---

## 1.5 II — Imposto de Importação

```
Aplica apenas para CFOP 3xxx (importação)

BC_II  = vAFRMM + vDespAdu + vCIF
         [BC = valor aduaneiro]
vII    = BC_II × pII / 100
vIOF   = vII × pIOF / 100   (quando aplicável)

Campos:
  vDespAdu  despesas aduaneiras
  vCIF      CIF (custo + seguro + frete)
  vAFRMM    adicional ao frete renovação marinha mercante
  vIOF      imposto sobre operações financeiras
```

---

## 1.6 IBS, CBS e IS — Reforma Tributária (NT 2025.002 v1.40)

### Cronograma

| Data | Evento |
|------|--------|
| Jan/2026 | Fase de testes — campos opcionais, sem rejeição |
| 01/07/2026 | Homologação CRT 3 obrigatório |
| 03/08/2026 | Produção CRT 3 obrigatório — rejeição ativa |
| 05/10/2026 | Devoluções referenciam DFeReferenciado obrigatoriamente |
| 04/01/2027 | Produção CRT 1/2 (SN/MEI) obrigatório |

### Característica fundamental

```
IBS e CBS são tributos "por fora" — somam ao total da nota
Diferente do ICMS que está embutido no preço (por dentro)

vNF_novo = vProd + vST + vFrete + vSeg + vII + vIPI
         + vPIS + vCOFINS + vOutro - vDesc
         + vIBS + vCBS + vIS      ← somam separadamente
```

### Estrutura Grupo UB (IBS/CBS/IS por item)

```xml
<IBSCBS>
  <CST>000</CST>                    <!-- CST IBS/CBS -->
  <cClassTrib>000001</cClassTrib>   <!-- classificação tributária -->
  <cIndOp>01</cIndOp>               <!-- indicador operação (NT 2025.002 v1.40) -->
  <indZFMALC>0</indZFMALC>          <!-- ZFM: 1=sim (CBS zero) -->

  <!-- IBS -->
  <IBS>
    <gIBSUF>
      <pAliqUF>XX.XXXX</pAliqUF>    <!-- alíquota estadual -->
      <vIBSUF>0.00</vIBSUF>
    </gIBSUF>
    <gIBSMun>
      <pAliqMun>XX.XXXX</pAliqMun>  <!-- alíquota municipal -->
      <vIBSMun>0.00</vIBSMun>
    </gIBSMun>
    <vIBS>0.00</vIBS>               <!-- total IBS = gIBSUF + gIBSMun -->
  </IBS>

  <!-- CBS -->
  <CBS>
    <pAliqCBS>XX.XXXX</pAliqCBS>
    <vCBS>0.00</vCBS>
  </CBS>

  <!-- IS — Imposto Seletivo (quando aplicável) -->
  <IS>
    <cClassTribIS>XXXXXX</cClassTribIS>
    <pAliqIS>XX.XXXX</pAliqIS>
    <vIS>0.00</vIS>
  </IS>
</IBSCBS>
```

### Tabela CST IBS/CBS

| CST | Descrição |
|-----|-----------|
| 000 | Tributação integral |
| 100 | Tributação com redução de alíquota |
| 200 | Tributação com redução de base de cálculo |
| 300 | Tributação monofásica |
| 400 | Diferimento |
| 500 | Imunidade |
| 600 | Isenção |
| 700 | Não incidência |
| 800 | Suspensão |
| 900 | Outros |

### Fórmulas IBS/CBS

```
BC_IBS = BC_CBS = vProd - vDesc   [mesma base por item]

vCBS      = BC_CBS × pAliqCBS / 100
vIBSUF    = BC_IBS × pAliqUF / 100
vIBSMun   = BC_IBS × pAliqMun / 100
vIBS      = vIBSUF + vIBSMun

# IS (Imposto Seletivo — bens e serviços específicos)
vIS = BC_IS × pAliqIS / 100

# ZFM (NT 007/2026):
  indZFMALC = 1 → pAliqCBS = 0 → vCBS = 0
  IBS mantém alíquota normal

# Cashback (gDevTrib NT 2025.002 v1.40):
  Grupo de devolução de tributos — pagamentos para consumidor final de baixa renda
  vDevTrib = valor CBS devolvido por operação
```

### campo cIndOp (B25d — NT 2025.002 v1.40)

| Código | Descrição |
|--------|-----------|
| 01 | Operação interna |
| 02 | Operação interestadual |
| 03 | Operação de importação |
| 04 | Operação de exportação |
| 05 | Operação com ZFM/ALC |
| 06 | Operação com área de livre comércio |

---

## 1.7 Totalizadores NF-e (ICMSTot)

```
vBC       soma de BC_ICMS de todos os itens
vICMS     soma de vICMS
vICMSDeson soma de vICMSDeson
vFCP      soma de vFCP
vBCST     soma de BC_ST
vST       soma de vICMSST
vFCPST    soma de vFCPST
vFCPSTRet soma de vFCPSTRet
vProd     soma de (vProd - vDesc) dos itens [ATENÇÃO: vDesc já deduzido]
          ← na prática: soma de vProd dos itens (sem desc)
vFrete    soma dos fretes rateados
vSeg      soma dos seguros rateados
vII       soma dos II
vIPI      soma dos IPI
vIPIDevol valor IPI devolvido (nas devoluções)
vPIS      soma dos PIS
vCOFINS   soma dos COFINS
vDesc     soma de todos os descontos
vOutro    soma de outros valores
vNF       = vProd - vDesc + vST + vFrete + vSeg + vII + vIPI + vPIS + vCOFINS + vOutro
            + vIBS + vCBS + vIS   [desde ago/2026]

vTotTrib  estimativa total de tributos (obrigatório — usar tabela IBPT)

# Rejeição 610: vNF deve bater com somatório de itens com indTot=1
# Tolerância: ± R$ 0,01
```

---

## 1.8 Regras de validação críticas NF-e

### Consistência entre campos

```
VAL-001: CRT 3 → CST (dois dígitos); CRT 1/2 → CSOSN (três dígitos)
VAL-002: CFOP 1xxx/2xxx/3xxx → tpNF=0 (entrada); CFOP 5xxx/6xxx/7xxx → tpNF=1 (saída)
VAL-003: CFOP 7xxx → idDest=3 (exterior), exporta obrigatório
VAL-004: CFOP 6xxx → idDest=2 (interestadual)
VAL-005: indFinal=1 + indIEDest=9 + UF diferente → grupo ICMSUFDest obrigatório
VAL-006: IPI apenas para CFOP industrialização/importação (não para CFOP comercialização simples)
VAL-007: CEST obrigatório quando produto sujeito a ST (Convênio ICMS 92/2015)
VAL-008: Série 890–899 reservada para contingência NFC-e — não usar em NF-e
VAL-009: dhEmi máx 5 minutos no futuro em relação ao servidor SEFAZ
VAL-010: CNPJ alfanumérico aceito a partir de jul/2026
VAL-011: Regime Normal → IBS/CBS obrigatório desde 03/08/2026
VAL-012: Devolução referencia nota original via chNFe ou nFref
VAL-013: Nota complementar → finNFe=2, deve referenciar nota original
VAL-014: nNF sequencial — duplicidade (mesmo CNPJ+série+número) → rejeição 204
VAL-015: vNF = somatório itens indTot=1 ± 0,01 (rejeição 610)
VAL-016: pMVAST interestadual → usar MVA ajustado (fórmula convênio)
VAL-017: CST 10/70 com ST → vICMSST não pode ser negativo → truncar em 0
VAL-018: Importação → II obrigatório para CFOP 3xxx com produto sujeito
VAL-019: indIntermed=1 → infIntermed (CNPJ intermediador + idCadIntTran) obrigatório
VAL-020: tpEmis=2-9 (contingência) → dhCont e xJust obrigatórios
```

---

# PARTE 2 — NFC-e (Modelo 65)

---

## 2.1 Diferenças estruturais em relação à NF-e

```
mod         = 65 (fixo)
tpImp       = 4 (DANFE NFC-e)
idDest      = 1 (sempre operação interna — venda presencial)
indPres     = 1 (presencial) ou 4 (delivery domicílio)
```

---

## 2.2 ICMS na NFC-e

```
Regras iguais à NF-e com exceções:
  - CST 60 na NFC-e: pST SEMPRE obrigatório (toda venda NFC-e é consumidor final)
  - DIFAL: NFC-e é sempre interna (idDest=1), portanto DIFAL NÃO se aplica
  - Contingência: tpEmis=9 (offline), dhCont + xJust obrigatórios
```

---

## 2.3 IPI na NFC-e

```
IPI NÃO se aplica à NFC-e
Regra: NFC-e é exclusivamente para venda a consumidor final
  → não gera crédito de IPI ao adquirente
  → grupo IPI deve ser omitido
```

---

## 2.4 Destinatário na NFC-e

```
Sem identificação:
  → omitir grupo dest inteiramente
  → limite: vNF ≤ R$ 10.000,00

Com CPF:
  → informar CPF (11 dígitos)
  → xNome opcional
  → endDest opcional

Com CNPJ (Ajuste SINIEF 12/2026 — permitido desde jul/2026):
  → informar CNPJ (desde que seja consumidor final)
  → NF-e modelo 55 continua obrigatória para operações B2B tradicionais

indIEDest = 9 sempre (consumidor final não contribuinte)
```

---

## 2.5 QR Code — NFC-e

```
Versão 3 (NT 2025.001 — obrigatória desde 2025)

Composição do QR Code:
  URL_base?chNFe={44 dígitos}&nVersao=100&tpAmb={1|2}&cDest={CPF|CNPJ sem pontuação}&dhEmi={hex}&vNF={valor sem ponto}&vICMS={valor sem ponto}&digVal={hash}&cIdToken={6 dígitos}

hashCSC = SHA-1( chave_acesso + cIdToken + CSC )
  CSC = Código de Segurança do Contribuinte (fornecido pela SEFAZ estadual)
  cIdToken = identificador do CSC (6 dígitos)

Regras de rejeição relacionadas a QR Code:
  656 = CSC inválido
  657 = CSC revogado
  659 = hash QR Code inválido
  660 = versão QR Code inválida
  661 = identificador CSC inválido
```

---

## 2.6 Pagamento — NFC-e (obrigatório)

```
Ao menos 1 detPag obrigatório

tPag válidos:
  01 = Dinheiro
  02 = Cheque
  03 = Cartão de Crédito
  04 = Cartão de Débito
  05 = Crédito Loja
  10 = Vale Alimentação
  11 = Vale Refeição
  12 = Vale Presente
  13 = Vale Combustível
  14 = Duplicata Mercantil
  15 = Boleto Bancário
  16 = Depósito Bancário
  17 = PIX — Pagamento Instantâneo
  18 = Transferência bancária, Carteira Digital
  19 = Programa de fidelidade, Cashback, Crédito Virtual
  90 = Sem Pagamento
  99 = Outros

tpIntegra (obrigatório para tPag 03 e 04):
  1 = Pagamento integrado com o sistema de automação (TEF)
  2 = Pagamento não integrado

Para cartão (tPag 03/04/05):
  CNPJ       CNPJ da credenciadora
  tBand      bandeira (01=Visa, 02=Mastercard, 03=American Express, 04=Sorocred,
              05=Diners, 06=Elo, 07=Hipercard, 08=Aura, 09=Cabal, 99=outros)
  cAut       código de autorização (NSU)

Regras:
  vTroco: obrigatório quando tPag=01 (dinheiro) e vPag > vNF
  Somatório vPag ≥ vNF (tolerância ±0,01)
  tPag=90 → vPag = 0 (sem pagamento — uso em NFC-e de ajuste)
```

---

## 2.7 Regras específicas NFC-e

```
NFC-001: vNF máximo = R$ 200.000,00 por nota
NFC-002: Sem destinatário → vNF máximo = R$ 10.000,00
NFC-003: Série 000–899 (900–999 reservado contingência offline)
NFC-004: IPI proibido — grupo IPI deve ser omitido
NFC-005: indPres = 1 (presencial) ou 4 (delivery)
NFC-006: idDest = 1 sempre (operação interna)
NFC-007: Carta de correção (CC-e) não admitida — somente cancelamento
NFC-008: Cancelamento: prazo máximo definido por estado (mínimo 30 min — verificar via API)
NFC-009: tpEmis=9 (contingência offline) → dhCont + xJust obrigatórios
NFC-010: QR Code versão 3 obrigatório
NFC-011: pST (CST 60) sempre obrigatório na NFC-e
NFC-012: DANFE NFC-e → largura mínima 58mm, legibilidade QR Code ≥ 6 meses
NFC-013: "Não permite aproveitamento de crédito de ICMS" — obrigatório no DANFE
NFC-014: Ajuste SINIEF 12/2026 — CNPJ de destinatário permitido desde jul/2026
```

---

# PARTE 3 — NFS-e Nacional (DPS v1)

---

## 3.1 ISS — regras gerais

### Base legal
LC 116/2003 — Lei Geral do ISS  
Alíquota mínima: 2% (art. 8-A, incluído pela LC 157/2016)  
Alíquota máxima: 5% (art. 8-A)  
Município competente: onde o serviço é prestado (regra geral) com exceções do art. 3º LC 116/2003

### Exceções ao município de prestação (art. 3º LC 116/2003)

ISS devido no **município do estabelecimento do prestador** para os serviços:

| Itens LC 116 | Serviço |
|-------------|---------|
| 3.04 | Locação, sublocação, arrendamento de coisas móveis |
| 7.02, 7.04, 7.05 | Execução de obra de construção civil em geral |
| 7.10 | Limpeza e dragagem |
| 11.01 | Guarda e estacionamento |
| 11.02 | Vigilância |
| 12 (exceto 12.13) | Espetáculos, diversões |
| 16.01 | Transporte coletivo municipal |
| 17.09 | Congresso, feira, exposição |

ISS devido no **município onde o serviço é prestado** (exceções do art. 3º):  
→ Construção civil, obras de engenharia, demolição (itens 7.02, 7.04, 7.05, 7.15)  
→ Serviços portuários, aeroportuários (itens 20.01, 20.03)

### Fórmulas ISS

```
# Regime Normal
BC_ISS  = vServ - vDescIncond - vDescCond
vISS    = BC_ISS × pAliq / 100

# Retenção pelo tomador
Quando tpRetISSQN = 1 (retido):
  vISSRet = vISS
  vLiq    = vServ - vISSRet - vRetCP - vRetIRRF - vRetCSLL

# Simples Nacional
  ISS embutido no DAS
  Campos de ISS na DPS: pAliq = 0, vISS = 0 (exceto quando retido pelo tomador)
  Exceção: ISS retido pelo tomador → deve ser destacado mesmo no SN
    pAliq = alíquota municipal normal (não a alíquota SN)
    vISS  = BC_ISS × pAliq / 100

# Serviços exportados (LC 116/2003 art. 2º, §3º)
  ISS = 0
  exigSusp ou ExigibilidadeISS = 4 (Exportação)
  Condição: resultado do serviço não pode ser usufruído no Brasil
```

---

## 3.2 PIS e COFINS na NFS-e

```
Regime Normal — Lucro Presumido (cumulativo):
  pPIS    = 0,65%
  pCOFINS = 3,00%

Regime Normal — Lucro Real (não-cumulativo):
  pPIS    = 1,65%
  pCOFINS = 7,60%

Simples Nacional:
  vPIS = 0, vCOFINS = 0

ATENÇÃO CRÍTICA (NT 007/2026):
  vPIS    = valor PIS DEVIDO na operação → BC_PIS × pPIS / 100
  vCOFINS = valor COFINS DEVIDO na operação → BC_COFINS × pCOFINS / 100
  Estes campos NUNCA representam valores retidos
  Valores retidos → vRetCSLL (campo consolidado)
```

### tpRetPisCofins — tabela NT 007/2026

| Código | Retenção |
|--------|----------|
| 0 | Sem retenção de PIS/COFINS |
| 1 | Retém apenas PIS |
| 2 | Retém apenas COFINS |
| 3 | Retém PIS e COFINS |
| 4 | Retém PIS, COFINS e CSLL |
| 5 | Retém apenas CSLL |
| 6 | Retém PIS e CSLL |
| 7 | Retém COFINS e CSLL |

**Regra consolidação:**  
Quando `tpRetPisCofins ∈ {4,5,6,7}` → os valores de CSLL retido integram `vRetCSLL`  
Quando `tpRetPisCofins = 4` → `vRetCSLL = vRetPIS + vRetCOFINS + vRetCSLL_puro`  
O campo `vRetCSLL` na DPS é o consolidado de todas as retenções que incluam CSLL

---

## 3.3 IRRF na NFS-e

```
Tabela de alíquotas (RIR/2018 — Art. 714 e ss.):

Tipo de serviço                              | Alíquota
---------------------------------------------|----------
Serviços profissionais liberais              | 1,50%
Assessoria creditícia/mercadológica          | 1,50%
Médicos, odontológicos, hospitalares         | 1,50%
Limpeza, conservação, segurança              | 1,00%
Vigilância                                   | 1,00%
Serviços de propaganda e publicidade         | 1,50%
Serviços técnicos especializados             | 1,50%
Construção civil (com empreitada de mão-obra)| 1,50%
Comissões e corretagens                      | 1,50%
Outros serviços (padrão)                     | 1,50%

Retenção obrigatória quando:
  - Tomador é Pessoa Jurídica
  - Valor do serviço ≥ R$ 215,05 (tabela IRPF anual — verificar atualização)
  - Serviço está na lista sujeita a retenção (IN RFB 1.234/2012)

vRetIRRF = vServ × pIRRF / 100
```

---

## 3.4 CSLL na NFS-e

```
Alíquota: 1%

Retenção obrigatória quando:
  - Serviços listados no art. 30 da Lei 10.833/2003
  - Tomador é Pessoa Jurídica
  - Valor do serviço ≥ R$ 215,05 (mesmo limite IRRF)

vRetCSLL_puro = vServ × 1% / 100

Consolidação NT 007/2026:
  Se tpRetPisCofins = 4 → vRetCSLL no campo = vRetPIS + vRetCOFINS + vRetCSLL_puro
```

---

## 3.5 CP/INSS pelo tomador na NFS-e

```
Base legal: art. 31 da Lei 8.212/1991

Alíquota: 11% (serviços com cessão de mão de obra)

Serviços sujeitos (lista art. 31 Lei 8.212/91 + IN RFB 971/2009):
  - Limpeza e conservação
  - Vigilância e segurança
  - Construção civil (empreitada total)
  - Transporte de passageiros
  - Digitação e preparação de dados
  - Manutenção e reparação de equipamentos
  - Copa e limpeza
  - Zeladoria
  - Treinamento e ensino
  - Saúde

vRetCP = BC_INSS × 11% / 100
BC_INSS = vServ (base normal)

Simples Nacional: retenção de INSS varia por serviço
  Serviços com cessão de mão de obra → tomador retém 3,5%
  Demais serviços → sem retenção pelo tomador
```

---

## 3.6 Liquidação da NFS-e

```
vLiq = vServ
     - vDescIncond
     - vDescCond
     - vISSRet      (se ISS retido)
     - vRetCP
     - vRetIRRF
     - vRetCSLL      (consolidado conforme tpRetPisCofins)

vLiq é o valor líquido a receber pelo prestador
```

---

## 3.7 IBS/CBS na NFS-e Nacional (NT 003/2025 a 009/2026)

### Princípio fundamental

```
Na DPS o prestador apenas DECLARA:
  - CST IBS/CBS
  - cClassTrib
  - cIndOp

O Ambiente Nacional (ADN/CGIBS) CALCULA as alíquotas e valores
O resultado volta no infNFSe (nota emitida pelo ambiente)

→ NÃO enviar alíquotas ou valores de IBS/CBS na DPS
→ Enviar apenas os códigos de classificação
```

### Grupo IBSCBS na DPS

```xml
<IBSCBS>
  <cIndOp>01</cIndOp>       <!-- indicador operação (tabela Anexo VII) -->
  <CST>000</CST>             <!-- código situação tributária -->
  <cClassTrib>000001</cClassTrib>
  <indZFMALC>0</indZFMALC>   <!-- Zona Franca Manaus (NT 007/2026) -->
  <finNFSe>0</finNFSe>       <!-- 0=normal, 1=crédito, 2=débito (NT 009/2026) -->
</IBSCBS>
```

### cIndOp — tabela Anexo VII NT 003/2025

| Código | Descrição |
|--------|-----------|
| 01 | Operação realizada no país — resultado aqui |
| 02 | Exportação de serviços — resultado no exterior |
| 03 | Operação com imunidade |
| 04 | Operação com isenção |
| 05 | Operação com não incidência |
| 06 | Operação com suspensão |
| 07 | Operação com redução de alíquota |
| 08 | Operação com ZFM/ALC (NT 007/2026) |

### finNFSe — NT 009/2026

| Código | Descrição |
|--------|-----------|
| 0 | Normal |
| 1 | Nota de crédito IBS/CBS |
| 2 | Nota de débito IBS/CBS |

### Obrigatoriedade

```
01/08/2026: grupo IBSCBS obrigatório para Regime Normal
Optantes SN: prazo específico a definir pelo CGIBS (posterior a jan/2027)
```

---

## 3.8 Exigibilidade ISS — casos especiais

### Suspensão por decisão judicial

```
exigSusp = 1
tpSusp   = 1 (judicial) ou 2 (administrativa)
nProcesso: número do processo (até 30 dígitos)
pAliq    : informar mesmo com suspensão
vISS     = 0 (não pagar enquanto suspenso)
```

### Imunidade

```
tpImunidade:
  0 = Sem imunidade
  1 = Imunidade — art. 150, VI, a (patrimônio/renda/serviços União/Estados/Municípios)
  2 = Imunidade — art. 150, VI, b (templos de qualquer culto)
  3 = Imunidade — art. 150, VI, c (partidos políticos, sindicatos, instituições educação)
  4 = Imunidade — art. 150, VI, d (livros, jornais, periódicos)

pAliq  = 0
vISS   = 0
```

### Exportação de serviços

```
ExigibilidadeISS = 4 (Exportação)
Condição LC 116/2003: resultado do serviço fruído no exterior
cPaisResult: código país onde o resultado é fruído
pAliq  = 0
vISS   = 0
```

### Regime especial de tributação

```
regEspTrib:
  0 = Nenhum
  1 = Ato cooperado (cooperativas de serviço)
  2 = Estimativa (ISS fixo mensal)
  3 = Microempresa municipal
  4 = Notário ou registrador
  5 = ME/EPP optante SN
  6 = MEI

Para regEspTrib = 1 (cooperativa):
  ISS calculado sobre o ato cooperado (distribuição ao cooperado)
  Alíquota incide sobre o diferencial entre o recebido e o repassado
```

---

## 3.9 Substituição de NFS-e — regras

```
Grupo subst na DPS:
  chSubstda = chave NFS-e a ser substituída (50 chars)
  cMotivo   = código de motivo (01-09)

Regras:
  SUB-001: NFS-e substituída deve estar no status "autorizada"
  SUB-002: NFS-e substituta deve ter mesma competência da substituída
  SUB-003: Motivo 01/02 (SN): mudança de regime retroativa
  SUB-004: Após substituição: NFS-e original passa a status "substituída"
  SUB-005: NFS-e substituta recebe nova chave de acesso
  SUB-006: Substituta não pode ter valor muito discrepante sem justificativa (regra do ambiente nacional)
  SUB-007: Prazo para substituição: sem prazo máximo legal, mas sujeito a condições municipais
```

---

## 3.10 Regras de validação NFS-e — completo

```
NFS-001: cNBS obrigatório — 9 dígitos (nomenclatura brasileira de serviços)
NFS-002: cLocIncid (município incidência ISS) ≠ cLocEmi é permitido — serviço prestado em outro município
NFS-003: opSimpNac = 1 (MEI) ou 2 (ME/EPP) para optantes SN
NFS-004: tpRetISSQN = 1 → vISSQN obrigatório e > 0
NFS-005: exigSusp = 1 → tpSusp + nProcesso obrigatórios
NFS-006: Grupo IBSCBS obrigatório desde 01/08/2026 (Regime Normal)
NFS-007: NÃO enviar valores de IBS/CBS — apenas CST + cClassTrib + cIndOp
NFS-008: vPIS = valor DEVIDO (não retido) — nunca retido
NFS-009: vCOFINS = valor DEVIDO (não retido) — nunca retido
NFS-010: tpRetPisCofins = 3 ou 4 → vRetCSLL inclui valores de PIS/COFINS retidos
NFS-011: substituição → chSubstda + cMotivo obrigatórios no grupo subst
NFS-012: finNFSe = 1 ou 2 (crédito/débito) → gIBSCBSAjuste obrigatório (NT 009/2026)
NFS-013: CNPJ alfanumérico aceito desde jul/2026
NFS-014: Exportação → cPaisResult obrigatório, ExigibilidadeISS = 4
NFS-015: Imunidade → tpImunidade 1-4, pAliq = 0, vISSQN = 0
NFS-016: indZFMALC = 1 → operação ZFM, CBS = zero pelo ADN
NFS-017: pAliq entre 2% e 5% (LC 116/2003) — exceção: SN, imunidade, isenção
NFS-018: vServ = vReceb + outros recebimentos (não pode ser zero se houver prestação)
NFS-019: dCompet (competência) não pode ser futura em mais de 1 mês
NFS-020: nDPS deve ser sequencial por emitente+série — lib não controla, ERP garante
NFS-021: Cooperativas → regEspTrib = 1 obrigatório
NFS-022: Retenção INSS SN com cessão mão de obra → alíquota tomador = 3,5% (não 11%)
NFS-023: cLocEmi = município do estabelecimento do prestador (não do prestador de fato)
NFS-024: Série alfanumérica — A-Z + 0-9, até 5 caracteres
```

---

## 3.11 Arredondamento — regra universal

```
Método: Banker's Rounding (arredondamento bancário / round half to even)
  0.005 → 0.00 (ímpar para baixo)
  0.015 → 0.02 (par para cima)
  0.025 → 0.02 (par, não arredonda)
  0.035 → 0.04

Tolerância SEFAZ:
  NF-e/NFC-e: ±R$ 0,01 no total
  NFS-e: ±R$ 0,01 por campo monetário

Casas decimais por campo:
  vUnCom, vUnTrib → 10 casas decimais
  Alíquotas (pICMS etc.) → 2 casas
  Alíquotas IBS/CBS → 4 casas
  Valores monetários → 2 casas
  Quantidade → 4 casas (qCom, qTrib)
```

---

## 3.12 Casos especiais — matriz de decisão

### Qual nota emitir?

| Situação | Documento |
|----------|-----------|
| Venda de produto para empresa (B2B) | NF-e mod 55 |
| Venda de produto para consumidor final presencial | NFC-e mod 65 |
| Venda de produto para consumidor final com entrega | NFC-e mod 65 (indPres=4) |
| Prestação de serviço | NFS-e Nacional |
| Venda de produto + serviço no mesmo documento | NF-e mod 55 (CFOP produto + ISSQN no item) |
| Exportação de mercadoria | NF-e mod 55 CFOP 7xxx |
| Exportação de serviço | NFS-e Nacional (cIndOp=02) |
| Devolução de venda | NF-e mod 55 finNFe=4 referenciando original |
| Nota complementar | NF-e mod 55 finNFe=2 |
| Transferência entre filiais | NF-e mod 55 CFOP 5152/6152 |

### Produto + serviço na mesma NF-e

```
Quando usar ISSQN no item de produto:
  - Produto tem componente de serviço (ex: software customizado)
  - CFOP do item indica serviço (ex: 5933)
  - ICMS substituído por ISSQN no grupo de impostos do item

Estrutura:
  det[n].imposto.ICMS → omitir (campo = "")
  det[n].imposto.ISSQN → preencher
    vBC       base de cálculo ISS
    vISS      valor ISS
    cMunFG    município incidência
    cListServ código LC 116
    indISS    1=exigível, 2=não incidente, ...
    indIncentivo 1=sim, 2=não

Totalizador ISSQN (ISSQNTot):
  vServ  total serviços sujeitos ao ISS
  vBC    base de cálculo
  vISS   total ISS
  vPIS   PIS sobre serviços
  vCOFINS COFINS sobre serviços
  dCompet competência (AAAA-MM)
  vDeducao deduções (construção civil)
  vOutro  outros valores
  vDescIncond desconto incondicionado
  vDescCond   desconto condicionado
  vISSRet ISS retido
```

---

*Documento gerado com base em:*
*NT NF-e 2025.002-RTC v1.40 · NT 2023.001 (combustíveis) · NT 2016.002 (FCP/DIFAL) · NT 2015.003 (totalizadores) · LC 214/2025 (IBS/CBS/IS) · LC 116/2003 (ISS) · Lei 8.212/1991 (INSS) · IN RFB 1.234/2012 · NT NFS-e 003/2025, 004 v2.00, 007/2026, 008/2026, 009/2026 · Ajuste SINIEF 12/2026 · Manual de Integração NFS-e Nacional v1.01*
