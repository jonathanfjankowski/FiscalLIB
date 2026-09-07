# Tabelas de Alíquotas — FiscalLib
## Referência para o TaxEngine

**Atualização:** setembro/2026  
**Fonte:** Res. Senado 22/1989 · Res. Senado 13/2012 · LC 214/2025 · LC 116/2003 · LC 227/2026 · legislações estaduais vigentes  

---

## 1. ICMS Interestadual — Tabela Cruzada 2026

Base: Resolução do Senado Federal nº 22/1989 (7% e 12%) e nº 13/2012 (4%)

### Regra geral

| Origem (UF) | Destino | Alíquota |
|-------------|---------|----------|
| SP, MG, RJ, PR, RS, SC | SP, MG, RJ, PR, RS, SC | **12%** |
| SP, MG, RJ, PR, RS, SC | AC, AL, AM, AP, BA, CE, DF, ES, GO, MA, MS, MT, PA, PB, PE, PI, RN, RO, RR, SE, TO | **7%** |
| AC, AL, AM, AP, BA, CE, DF, ES, GO, MA, MS, MT, PA, PB, PE, PI, RN, RO, RR, SE, TO | Qualquer UF | **12%** |
| Qualquer UF | Qualquer UF (mercadoria importada*) | **4%** |

*Mercadoria importada ou com conteúdo de importação > 40% — Res. Senado 13/2012

### Tabela cruzada completa (Origem × Destino)

Estados do **Sul/Sudeste** (origem): SP · MG · RJ · PR · RS · SC  
Estados das **demais regiões** (destino favorecido): AC · AL · AM · AP · BA · CE · DF · ES · GO · MA · MS · MT · PA · PB · PE · PI · RN · RO · RR · SE · TO

```
Se origem ∈ {SP, MG, RJ, PR, RS, SC}:
  Se destino ∈ {SP, MG, RJ, PR, RS, SC} → 12%
  Se destino ∈ demais → 7%

Se origem ∈ demais:
  Qualquer destino → 12%

Se mercadoria importada (origem=4,5,6,7,8 na tabela de origem):
  Qualquer trajeto → 4%
```

### Código de origem da mercadoria (campo `orig`)

| Código | Descrição | Alíquota inter |
|--------|-----------|----------------|
| 0 | Nacional | 7% ou 12% |
| 1 | Estrangeira — importação direta | 4% |
| 2 | Estrangeira — adquirida no mercado interno | 4% |
| 3 | Nacional — mercadoria ou bem com conteúdo de importação > 40% | 4% |
| 4 | Nacional — produção conforme processo produtivo básico (PPB) | 7% ou 12% |
| 5 | Nacional — mercadoria ou bem com conteúdo de importação ≤ 40% | 7% ou 12% |
| 6 | Estrangeira — importação direta, sem similar nacional | 4% |
| 7 | Estrangeira — adquirida no mercado interno, sem similar nacional | 4% |
| 8 | Nacional — mercadoria ou bem com conteúdo de importação > 70% | 4% |

---

## 2. ICMS Interno — Alíquotas Padrão por Estado (2026)

> ⚠️ Alíquotas internas variam por produto. As abaixo são as **alíquotas gerais** (regra geral).  
> Produtos como bebidas alcoólicas, cigarros, combustíveis, energia elétrica e telecomunicações têm alíquotas diferenciadas (geralmente 25–29%).  
> Validar sempre o RICMS do estado e possíveis benefícios fiscais.

| UF | Estado | Alíquota Geral | FCP/FECP adicional | Total efetivo |
|----|--------|---------------|---------------------|---------------|
| AC | Acre | 19,00% | — | 19,00% |
| AL | Alagoas | 20,50% | 1,00% (FECOEP) | 21,50% |
| AM | Amazonas | 20,00% | — | 20,00% |
| AP | Amapá | 18,00% | — | 18,00% |
| BA | Bahia | 20,50% | — | 20,50% |
| CE | Ceará | 20,00% | — | 20,00% |
| DF | Distrito Federal | 20,00% | — | 20,00% |
| ES | Espírito Santo | 17,00% | — | 17,00% |
| GO | Goiás | 19,00% | — | 19,00% |
| MA | Maranhão | 23,00% | — | 23,00% |
| MG | Minas Gerais | 18,00% | — | 18,00% |
| MS | Mato Grosso do Sul | 17,00% | — | 17,00% |
| MT | Mato Grosso | 17,00% | — | 17,00% |
| PA | Pará | 19,00% | — | 19,00% |
| PB | Paraíba | 20,00% | — | 20,00% |
| PE | Pernambuco | 20,50% | — | 20,50% |
| PI | Piauí | 22,50% | — | 22,50% |
| PR | Paraná | 19,50% | — | 19,50% |
| RJ | Rio de Janeiro | 20,00% | 2,00% (FECP) | 22,00% |
| RN | Rio Grande do Norte | 20,00% | — | 20,00% |
| RO | Rondônia | 19,50% | — | 19,50% |
| RR | Roraima | 20,00% | — | 20,00% |
| RS | Rio Grande do Sul | 17,00% | — | 17,00% |
| SC | Santa Catarina | 17,00% | — | 17,00% |
| SE | Sergipe | 19,00% | 1,00% (FECOEP) | 20,00% |
| SP | São Paulo | 18,00% | — | 18,00% |
| TO | Tocantins | 20,00% | — | 20,00% |

### Alíquotas diferenciadas comuns (referência, validar no RICMS estadual)

| Produto/Serviço | Alíquota típica |
|-----------------|----------------|
| Energia elétrica (residencial baixo consumo) | 12–18% |
| Energia elétrica (demais) | 25–29% |
| Telecomunicações | 25–30% |
| Combustíveis (gasolina, diesel, etanol) | monofásico (NT 2023.001) |
| Bebidas alcoólicas | 25–29% |
| Cigarros / fumo | 25–29% |
| Armas e munições | 25% |
| Vestuário / calçados | alíquota geral |
| Alimentos da cesta básica | 0–7% (varia por estado) |
| Medicamentos | 0–12% (varia por estado) |

---

## 3. DIFAL 2026 — Tabela de Referência por Destino

> Aplica para: tpNF=saída + indFinal=1 (consumidor final) + indIEDest=9 (não contribuinte) + UF diferente

| UF Destino | Alíquota Interna | pICMSInter (Sul/SE→destino) | DIFAL (Sul/SE origem) | pICMSInter (outros→destino) | DIFAL (outros origem) |
|------------|-----------------|-----------------------------|-----------------------|------------------------------|-----------------------|
| AC | 19,00% | 7% | 12,00% | 12% | 7,00% |
| AL | 21,50% | 7% | 14,50% | 12% | 9,50% |
| AM | 20,00% | 7% | 13,00% | 12% | 8,00% |
| AP | 18,00% | 7% | 11,00% | 12% | 6,00% |
| BA | 20,50% | 7% | 13,50% | 12% | 8,50% |
| CE | 20,00% | 7% | 13,00% | 12% | 8,00% |
| DF | 20,00% | 7% | 13,00% | 12% | 8,00% |
| ES | 17,00% | 7% | 10,00% | 12% | 5,00% |
| GO | 19,00% | 7% | 12,00% | 12% | 7,00% |
| MA | 23,00% | 7% | 16,00% | 12% | 11,00% |
| MG | 18,00% | 12% | 6,00% | 12% | 6,00% |
| MS | 17,00% | 7% | 10,00% | 12% | 5,00% |
| MT | 17,00% | 7% | 10,00% | 12% | 5,00% |
| PA | 19,00% | 7% | 12,00% | 12% | 7,00% |
| PB | 20,00% | 7% | 13,00% | 12% | 8,00% |
| PE | 20,50% | 7% | 13,50% | 12% | 8,50% |
| PI | 22,50% | 7% | 15,50% | 12% | 10,50% |
| PR | 19,50% | 12% | 7,50% | 12% | 7,50% |
| RJ | 22,00% | 12% | 10,00% | 12% | 10,00% |
| RN | 20,00% | 7% | 13,00% | 12% | 8,00% |
| RO | 19,50% | 7% | 12,50% | 12% | 7,50% |
| RR | 20,00% | 7% | 13,00% | 12% | 8,00% |
| RS | 17,00% | 12% | 5,00% | 12% | 5,00% |
| SC | 17,00% | 12% | 5,00% | 12% | 5,00% |
| SE | 20,00% | 7% | 13,00% | 12% | 8,00% |
| SP | 18,00% | 12% | 6,00% | 12% | 6,00% |
| TO | 20,00% | 7% | 13,00% | 12% | 8,00% |

**Fórmula resumida:**
```
DIFAL = (alíquota interna destino - alíquota interestadual) × BC_ICMS / 100
Todo o DIFAL vai para o estado de destino (partilha permanente pós-2019)
FCP DIFAL = BC_ICMS × pFCP_UF_destino / 100
```

---

## 4. FCP por Estado (2026)

> FCP = Fundo de Combate à Pobreza — adicional ao ICMS  
> Máximo constitucional: 2% (EC 132/2023)  
> Incide sobre produtos específicos — não sobre todos os itens

| UF | % FCP/FECP | Produtos típicos sujeitos |
|----|-----------|--------------------------|
| AL | 1,00% | Bebidas alcoólicas, fumo, telecomunicações |
| CE | 2,00% | Bebidas, fumo, energia, telecom |
| MG | 2,00% | Bebidas, fumo, energia, telecom |
| MS | 2,00% | Bebidas, fumo, telecom |
| MT | 2,00% | Bebidas, fumo, telecom |
| PA | 2,00% | Bebidas, fumo |
| PE | 2,00% | Bebidas, fumo, telecom |
| PI | 2,00% | Bebidas, fumo |
| RJ | 2,00% | Todos os itens sujeitos ao ICMS (FECP geral) |
| RN | 2,00% | Bebidas, fumo, telecom |
| RS | 0,50% | Bebidas, fumo |
| SE | 1,00% | Bebidas alcoólicas, fumo (FECOEP) |
| SP | 2,00% | Bebidas, fumo |
| TO | 2,00% | Bebidas, fumo |
| Demais | — | Verificar legislação estadual |

---

## 5. PIS e COFINS — Alíquotas por Regime

### Regime Cumulativo (Lucro Presumido e Simples exceto)

| Tributo | Alíquota padrão | Base legal |
|---------|----------------|------------|
| PIS | 0,65% | Lei 9.718/1998 |
| COFINS | 3,00% | Lei 9.718/1998 |

### Regime Não-Cumulativo (Lucro Real)

| Tributo | Alíquota padrão | Base legal |
|---------|----------------|------------|
| PIS | 1,65% | Lei 10.637/2002 |
| COFINS | 7,60% | Lei 10.833/2003 |

### Simples Nacional

| Tributo | Alíquota | Observação |
|---------|----------|------------|
| PIS | 0% | Embutido no DAS — CST 07 na nota |
| COFINS | 0% | Embutido no DAS — CST 07 na nota |

### Alíquotas monofásicas (produtos específicos)

| Produto | PIS | COFINS | Base legal |
|---------|-----|--------|------------|
| Gasolina (exceto aviação) | 10,20% | 47,40% | Lei 9.990/2000 |
| Óleo diesel | 8,60% | 39,84% | Lei 9.990/2000 |
| GLP | 10,20% | 47,20% | Lei 9.990/2000 |
| Querosene de aviação | 5,00% | 23,20% | Lei 9.990/2000 |
| Álcool para fins carburantes | 1,50% | 6,90% | Lei 9.990/2000 |
| Medicamentos | 2,10% | 9,90% | Lei 10.147/2000 |
| Perfumes e cosméticos | 2,20% | 10,30% | Lei 10.147/2000 |
| Pneus | 2,00% | 9,50% | Lei 10.485/2002 |
| Autopeças | 2,30% | 10,80% | Lei 10.485/2002 |

---

## 6. IPI — Alíquotas por NCM (referência — usar tabela TIPI vigente)

> A tabela TIPI completa tem mais de 10.000 itens. Abaixo os grupos mais comuns.  
> Fonte: Decreto 11.158/2022 e atualizações posteriores.  
> **A lib não embute a TIPI completa — o ERP deve fornecer a alíquota do produto pelo NCM.**

| Grupo de produtos | Faixa de alíquota IPI | Observação |
|-------------------|-----------------------|------------|
| Alimentos em geral | 0% | Maioria isenta |
| Bebidas alcoólicas | 20–60% | Varia por tipo |
| Cigarros | 300% | Específico |
| Perfumes e cosméticos | 7–15% | |
| Medicamentos | 0–10% | Maioria isenta |
| Têxteis / vestuário | 0–15% | |
| Calçados | 0–15% | |
| Eletrodomésticos | 5–20% | |
| Eletrônicos / informática | 0–15% | |
| Automóveis (1000cc) | 7% | |
| Automóveis (1000–2000cc) | 11–13% | |
| Automóveis (>2000cc) | 18–25% | |
| Combustíveis | 0% (monofásico ICMS) | |
| Produtos de borracha | 5–15% | |
| Papel e celulose | 0–10% | |
| Plásticos | 8–15% | |
| Metais comuns | 0–10% | |
| Máquinas e equipamentos | 0–15% | |
| Armas e munições | 10–150% | |

---

## 7. ISS — Alíquotas mínima e máxima (LC 116/2003)

| Limite | Alíquota | Base legal |
|--------|----------|------------|
| Mínimo nacional | 2,00% | Art. 8-A LC 116/2003 (incluído pela LC 157/2016) |
| Máximo nacional | 5,00% | Art. 8-A LC 116/2003 |

> **A alíquota real é definida por cada município.**  
> O TaxEngine recebe a alíquota configurada no ERP — não calcula por município.  
> O campo `cLocIncid` na DPS indica o município de incidência.

### ISS no Simples Nacional — alíquota efetiva por faixa (Anexo III LC 123/2006)

| Faixa | Receita Bruta Anual | Alíquota efetiva SN | Percentual ISS do DAS |
|-------|---------------------|---------------------|-----------------------|
| 1ª | Até R$ 180.000 | 6,00% | 33,50% do DAS |
| 2ª | De R$ 180.001 a R$ 360.000 | 11,20% | calculado |
| 3ª | De R$ 360.001 a R$ 720.000 | 13,20% | calculado |
| 4ª | De R$ 720.001 a R$ 1.800.000 | 16,00% | calculado |
| 5ª | De R$ 1.800.001 a R$ 3.600.000 | 21,00% | calculado |
| 6ª | De R$ 3.600.001 a R$ 4.800.000 | 33,00% | calculado |

> Para SN a nota fiscal não destaca ISS (exceto quando retido pelo tomador).  
> Quando retido: usar a **alíquota municipal normal** do município de incidência.

---

## 8. IRRF — Alíquotas por Tipo de Serviço (IN RFB 1.234/2012)

| Tipo de serviço | Alíquota |
|-----------------|----------|
| Serviços profissionais liberais (médicos, advogados, engenheiros, etc.) | 1,50% |
| Assessoria creditícia, mercadológica, gestão de crédito | 1,50% |
| Serviços hospitalares e odontológicos | 1,50% |
| Auditoria, contabilidade, consultoria | 1,50% |
| Serviços de informática e TI | 1,50% |
| Publicidade e propaganda | 1,50% |
| Pesquisa e desenvolvimento | 1,50% |
| Construção civil (empreitada de mão de obra) | 1,50% |
| Limpeza e conservação | 1,00% |
| Vigilância e segurança | 1,00% |
| Transporte de carga | 1,50% |
| Transporte de passageiros | 1,50% |
| Comissões e corretagens | 1,50% |
| Intermediação de negócios | 1,50% |
| Arrendamento mercantil (leasing) | 1,50% |
| Factoring | 1,50% |
| Serviços de manutenção e reparo | 1,50% |
| Treinamento e cursos | 1,50% |

**Limite de retenção:** valor da fatura ≥ R$ 215,05 (verificar atualização anual)  
**Retenção sobre:** valor bruto da nota, antes dos descontos

---

## 9. CSLL — Retenção na Fonte (Lei 10.833/2003, art. 30)

| Alíquota | Serviços |
|----------|----------|
| 1,00% | Todos os serviços sujeitos à retenção na fonte (lista art. 30) |

**Lista de serviços sujeitos:**  
Limpeza, conservação, dedetização, asseio, jardinagem, vigilância, segurança, transporte de valores e locação de mão de obra; assessoria creditícia, mercadológica; factoring; serviços de suporte operacional; projetos, cálculos, desenhos; serviços de programação e suporte em tecnologia; instalação, manutenção e reparação.

**Mesmo limite de IRRF:** fatura ≥ R$ 215,05

---

## 10. INSS (CP) — Retenção pelo Tomador (Lei 8.212/1991, art. 31)

| Modalidade | Alíquota | Aplicação |
|------------|----------|-----------|
| Regime Normal (PJ) | 11,00% | Cessão de mão de obra |
| Simples Nacional | 3,50% | Cessão de mão de obra (Lei 12.546/2011) |

**Serviços com cessão de mão de obra (obriga retenção):**

| Serviço | Base legal |
|---------|------------|
| Limpeza e conservação | IN RFB 971/2009, art. 117 |
| Vigilância e segurança | art. 117 |
| Construção civil (empreitada total com mão de obra) | art. 117 |
| Transporte de passageiros (por empresa) | art. 117 |
| Digitação, preparação de dados para processamento | art. 117 |
| Manutenção e reparação de equipamentos | art. 117 |
| Copa, limpeza e zeladoria | art. 117 |
| Treinamento e ensino (com cessão de instrutores) | art. 117 |
| Saúde (com cessão de profissionais) | art. 117 |
| Serviços de call center | art. 117 |
| Engenharia e arquitetura (com cessão) | art. 117 |
| Tecnologia da informação (com cessão de profissionais) | art. 117 |

**Base de cálculo INSS:** valor bruto da nota, sem deduções  
**Prazo recolhimento:** até o dia 20 do mês seguinte ao pagamento

---

## 11. IBS e CBS — Tabela de Transição 2026–2033

### Alíquotas de teste 2026 (informativas — sem cobrança efetiva)

| Tributo | Alíquota 2026 | Competência | Observação |
|---------|--------------|-------------|------------|
| CBS | 0,90% | Federal | Compensável com PIS/COFINS devidos |
| IBS estadual | 0,05% | Estadual | |
| IBS municipal | 0,05% | Municipal | |
| **IBS total** | **0,10%** | Est.+Mun. | |

### Cronograma de transição (LC 214/2025 + LC 227/2026)

| Ano | CBS | IBS | ICMS | ISS | PIS/COFINS | IPI |
|-----|-----|-----|------|-----|------------|-----|
| 2026 | 0,9% (teste) | 0,1% (teste) | 100% | 100% | 100% | 100% |
| 2027 | ref. - 0,1pp | 0,1% | 100% | 100% | **extintos** | alíq. zero* |
| 2028 | ref. - 0,1pp | 0,1% | 90% | 90% | — | zero* |
| 2029 | Senado define | Senado define | 80% | 80% | — | zero* |
| 2030 | — | — | 70% | 70% | — | zero* |
| 2031 | — | — | 60% | 60% | — | zero* |
| 2032 | — | — | 40% | 40% | — | zero* |
| 2033 | **8,80%** | **17,70%** | **extintos** | **extintos** | — | zero* |

*IPI alíquota zero exceto produtos da Zona Franca de Manaus  
**Total IBS+CBS estimado em 2033: 26,50%** (sujeito a ajuste pelo Senado)

### Alíquotas reduzidas IBS/CBS (LC 214/2025)

| Redução | Setores |
|---------|---------|
| **100% (zero)** | Cesta básica nacional (arroz, feijão, carne bovina, peixe, ovos, leite, manteiga, queijo, óleo, farinha, macarrão, café, pão, açúcar, sal) |
| **60%** | Saúde, educação, transporte coletivo, produtos agropecuários básicos, dispositivos médicos, medicamentos, higiene pessoal básica, produtos de limpeza doméstica |
| **30%** | Serviços financeiros, seguros, serviços de saúde e educação não presenciais, insumos agropecuários |

### Para a FiscalLib em 2026

```
Para NF-e/NFC-e:
  pAliqCBS = 0.9     [fase de teste]
  pAliqIBSUF  = 0.05 [fase de teste]
  pAliqIBSMun = 0.05 [fase de teste]
  vCBS = BC × 0.9 / 100   [informativo]
  vIBS = BC × 0.1 / 100   [informativo]

Para NFS-e Nacional:
  NÃO enviar alíquotas nem valores na DPS
  O ADN (Ambiente Nacional) calcula e retorna no infNFSe
  Enviar apenas: CST + cClassTrib + cIndOp
```

---

## 12. Simples Nacional — Tabela de Alíquotas 2026

### Anexo III — Serviços (relevante para NFS-e)

| Faixa | Receita Bruta 12 meses | Alíquota | Dedução (R$) |
|-------|------------------------|----------|--------------|
| 1ª | Até 180.000 | 6,00% | — |
| 2ª | 180.001 a 360.000 | 11,20% | 9.360,00 |
| 3ª | 360.001 a 720.000 | 13,20% | 17.640,00 |
| 4ª | 720.001 a 1.800.000 | 16,00% | 35.640,00 |
| 5ª | 1.800.001 a 3.600.000 | 21,00% | 125.640,00 |
| 6ª | 3.600.001 a 4.800.000 | 33,00% | 648.000,00 |

### Limites Simples Nacional 2026

| Categoria | Receita Bruta Anual Máxima |
|-----------|---------------------------|
| MEI | R$ 81.000,00 |
| ME (Microempresa) | R$ 360.000,00 |
| EPP (Empresa de Pequeno Porte) | R$ 4.800.000,00 |

---

## 13. Arredondamento — Tabela de Casas Decimais por Campo

| Campo | Casas decimais | Método |
|-------|---------------|--------|
| vUnCom (valor unitário comercial) | 10 | Banker's rounding |
| vUnTrib (valor unitário tributável) | 10 | Banker's rounding |
| qCom, qTrib (quantidades) | 4 | Banker's rounding |
| pICMS, pIPI, pPIS, pCOFINS (alíquotas %) | 2 | Banker's rounding |
| pAliqCBS, pAliqIBS (alíquotas IBS/CBS) | 4 | Banker's rounding |
| vICMS, vIPI, vPIS, vCOFINS (valores R$) | 2 | Banker's rounding |
| vBC, vBCST (bases de cálculo R$) | 2 | Banker's rounding |
| vNF, vProd (totais R$) | 2 | Banker's rounding |
| vISS, vRetIRRF, vRetCSLL (NFS-e R$) | 2 | Banker's rounding |
| pAliq ISS (%) | 2 | Banker's rounding |

**Tolerância SEFAZ:** ±R$ 0,01 no total da nota (rejeição 610 / regra NFS-e equivalente)

---

## 14. O que a FiscalLib embute vs. o que o ERP fornece

### A lib embute (constantes — não mudam por operação)

- Regras de alíquota interestadual (7%/12%/4%) por origem
- Lógica de DIFAL (fórmula e partilha)
- Alíquotas padrão PIS/COFINS por regime tributário
- Alíquotas IRRF por tipo de serviço (IN 1.234/2012)
- CSLL 1% e INSS 11%/3,5% com lógica de obrigatoriedade
- Alíquotas de transição IBS/CBS 2026 (0,9%/0,1%)
- Tabela de origem da mercadoria (orig 0–8) → faixa interestadual
- Calendário de obrigatoriedade IBS/CBS por CRT

### O ERP deve fornecer (configurável por produto/serviço)

- Alíquota ICMS interna do estado do emitente para cada produto
- Alíquota ICMS de ST para cada UF de destino (por protocolo ICMS)
- MVA (Margem de Valor Agregado) por produto e UF destino
- Alíquota de FCP por produto e UF
- CST e CSOSN por produto
- Código de benefício fiscal estadual (cBenef) quando aplicável
- Alíquota ISS do município de incidência por serviço
- Alíquota IPI por NCM
- Alíquota IBS/CBS de referência por produto (CST/cClassTrib)
- Indicador de retenção de IRRF/CSLL/INSS por serviço

---

*Fonte: Resolução Senado 22/1989 · Resolução Senado 13/2012 · LC 87/1996 (Lei Kandir) · LC 116/2003 · LC 123/2006 · LC 190/2022 · LC 214/2025 · LC 227/2026 · IN RFB 1.234/2012 · IN RFB 971/2009 · Lei 8.212/1991 · Lei 9.718/1998 · Lei 10.637/2002 · Lei 10.833/2003 · Decreto 11.158/2022 (TIPI) · legislações estaduais RICMS vigentes em set/2026*
