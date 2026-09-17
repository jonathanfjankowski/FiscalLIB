# Contrato de payload e de extensão (EmissorInterface)

Dois contratos convivem nesta lib:

1. **Interno** — o modelo `Documento\*` (lingua franca entre builders e emissores).
2. **Externo** — o JSON que o adaptador FiscalAPI envia (`EmissaoRequest`/`NfseDpsRequest`).

```
ERP → TaxEngine → Builder → NfeDocumento|NfseDocumento → EmissorInterface
                                                            ├─► Adapters\FiscalApi  → JSON /v1
                                                            └─► sua implementação   → o que quiser
```

## 1. Modelo intermediário (`Documento\`)

Campos monetários/decimais são **strings** com casas fixas (`'100.00'`, `'18.0000'`) —
matemática exata, sem float. Principais tipos:

| Tipo | Papel |
|------|-------|
| `NfeDocumento` | NF-e/NFC-e pronta (modelo 55/65, itens, totais, pagamentos, NF-ref) |
| `NfseDocumento` | DPS NFS-e Nacional (tomador, serviço, valores, IBSCBS) |
| `ItemFiscal` | item + `NfeTaxResultado` (tributos calculados) |
| `TotaisDocumento` | totais com a fórmula v2 do contrato (`valorNota`) |
| `ResultadoEmissao` | consulta/resultado consolidado (`isTerminal()`, `exigirAutorizada()`) |
| `AceiteEmissao` | resposta imediata da submissão (`documentoId`) |
| `ResultadoEvento` | cancelamento/CC-e/inutilização |

## 2. JSON enviado à FiscalAPI (adaptador embutido)

Campos **camelCase**, decimais como **números JSON**, defaults do contrato aplicados
no mapeador (`gtin: "SEM GTIN"`, `unidade: "UN"`). Exemplo (CST 10 + ST + IPI):

```json
{
  "ambiente": "homologacao",
  "serie": 1,
  "itens": [{
    "codigo": "SKU1",
    "descricao": "Produto de teste",
    "quantidade": 2,
    "valorUnitario": 50,
    "valorTotal": 100,
    "gtin": "SEM GTIN",
    "unidade": "UN",
    "ncm": "12345678",
    "cfop": "5102",
    "impostosV2": {
      "icms": {
        "origem": 0, "cst": "10", "modBc": "3",
        "baseCalculo": 100, "aliquota": 18, "valor": 18,
        "st": { "modBcSt": "4", "baseCalculoSt": 130, "aliquotaSt": 18, "valorSt": 23.4 }
      },
      "ipi": { "cst": "50", "baseCalculo": 100, "aliquota": 10, "valor": 10, "cEnq": "999" },
      "pis":  { "cst": "01", "baseCalculo": 100, "aliquota": 1.65, "valor": 1.65 },
      "cofins": { "cst": "01", "baseCalculo": 100, "aliquota": 7.6, "valor": 7.6 }
    }
  }],
  "totais": { "valorProdutos": 100, "valorNota": 143.4, "valorFrete": 10 },
  "finalidade": "normal",
  "tipoOperacao": "saida",
  "indicadorConsumidorFinal": "sim",
  "pagamento": [{ "forma": "01", "valor": 100 }]
}
```

NF-e via marketplace/intermediador (NT 2020.006 — `indIntermed`, só mod 55): o builder
expõe `->intermediador(int $indicador, ?string $cnpj = null)` (0 = sem intermediador —
default da API; 1 = site/plataforma de terceiros, exige CNPJ) e o payload ganha
`"indicadorIntermediador": 1, "cnpjIntermediador": "..."` → grupo `infIntermed`.

NFS-e DPS: `ambiente`, `serie`, `tomador{...}`, `servico{codigoTributarioNacional,
descricaoServico, codigoNbs}`, `valores{valorServicos, tributacaoIssqn, retencaoIssqn,
aliquotaIssqn, tributacaoFederal{...}}`, `ibscbs{finalidade, codigoIndicadorOperacao,
gibbsCbs{cst, cClassTrib}}`.

Headers em todo POST: `Authorization: ApiKey <chave>` + `Idempotency-Key` (UUID v4
gerado pela lib se o ERP não fornecer). Erros seguem RFC 7807 e viram exceções
tipadas (`ValidacaoApiException` 400/422 com `campo`, `EstadoInvalidoException` 409, …).

## 3. Implementando seu próprio emissor

A porta é `FiscalLib\Contracts\EmissorInterface`:

```php
interface EmissorInterface
{
    public function emitir(NfeDocumento|NfseDocumento $documento, ?OpcoesEmissao $opcoes = null): AceiteEmissao;
    public function consultar(string $documentoId): ResultadoEmissao;
    public function cancelar(string $documentoId, string $justificativa, ?OpcoesEvento $opcoes = null): ResultadoEvento;
    public function cartaCorrecao(string $documentoId, string $correcao, ?OpcoesEvento $opcoes = null): ResultadoEvento;
    public function inutilizar(InutilizacaoPedido $pedido, ?OpcoesEvento $opcoes = null): ResultadoEvento;
    public function consultarInutilizacao(string $eventoId): ResultadoEvento;
    public function baixarPdf(string $documentoId, bool $emBase64 = false): ArquivoPdf;
    public function substituir(string $documentoId, NfseDocumento $substituta, int $cMotivo, ?string $xMotivo = null, ?OpcoesEmissao $opcoes = null): AceiteEmissao;
}
```

Diretrizes para implementar:

1. **Receba o modelo tipado** — nunca o JSON do adaptador. Todo o dado fiscal já
   está validado em `Documento\*` (incl. `ItemFiscal->tributos->paraArray()`).
2. **`emitir()` = submissão** — devolva `AceiteEmissao` com o identificador do
   documento; o polling até terminal é feito pelo núcleo (`AguardadorTerminal`)
   via `consultar()`. Se o seu transporte for síncrono, devolva o resultado
   imediatamente em `consultar()`.
3. **`ResultadoEmissao->status`** deve usar os valores canônicos
   (`PENDENTE`, `PROCESSANDO`, `AUTORIZADA`, `REJEITADA`, `CONTINGENCIA`,
   `CANCELAMENTO_PENDENTE`, `CANCELADA`, `DENEGADA`, `ERRO_INTERNO`) para que
   `isTerminal()`/`aguardarTerminal()` funcionem. Status novos da sua API:
   informe `statusDocumento: null` (não-terminal) ou trate no seu domínio.
4. **Honre a idempotência** — `OpcoesEmissao->idempotencyKey` deve ser estável
   entre retries da mesma tentativa lógica.
5. **Erros** — lance as exceções de `FiscalLib\Exceptions\` para que o ERP trate
   de forma uniforme entre emissores.

O adaptador `Adapters\FiscalApi\EmissorFiscalApi` (≈200 linhas) é a referência
de implementação. Os testes em `tests/Port/EmissorFakeTest` mostram o núcleo
funcionando contra um emissor sem qualquer HTTP.

## 4. O que o núcleo NÃO faz

Assinatura XML, transmissão SEFAZ, certificado digital, DANFE, numeração
(reservada pelo emissor), contingência e guarda de XML são responsabilidade do
emissor. Esta lib entrega: cálculo, regras, modelo e o contrato de transporte.
