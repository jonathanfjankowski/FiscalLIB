<?php

declare(strict_types=1);

namespace FiscalLib\Nfe;

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\FinalidadeNfe;
use FiscalLib\Common\Enums\FormaPagamento;
use FiscalLib\Common\Enums\IndicadorConsumidorFinal;
use FiscalLib\Common\Enums\IndicadorIntermediador;
use FiscalLib\Common\Enums\IndicadorPresenca;
use FiscalLib\Common\Enums\ModeloDocumento;
use FiscalLib\Common\Enums\TipoOperacao;
use FiscalLib\Common\Matematica;
use FiscalLib\Common\ValueObjects\CodigoCfop;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Common\ValueObjects\ChaveAcesso;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\Emitente;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\Documento\NfeDocumento;
use FiscalLib\Documento\NfeReferenciada;
use FiscalLib\Documento\Pagamento;
use FiscalLib\Documento\TotaisDocumento;
use FiscalLib\Exceptions\MissingFieldException;
use FiscalLib\Exceptions\TaxInconsistencyException;
use FiscalLib\Exceptions\ValidationException;

/**
 * Builder de NF-e (modelo 55). Fluent, valida regras em build() e devolve
 * o modelo intermediário NfeDocumento — pronto para qualquer EmissorInterface.
 *
 * Regras: R001/R002 (CFOP × tpNF), R003/R004 (DIFAL fica no TaxEngine),
 * R012 invertido (numeração é do emissor), devolução exige NF-ref (v2 F4).
 *
 * @phpstan-consistent-constructor
 */
class NfeBuilder
{
    protected Ambiente $ambiente = Ambiente::Homologacao;
    protected int $serie = 1;
    protected ?string $naturezaOperacao = null;
    protected FinalidadeNfe $finalidade = FinalidadeNfe::Normal;
    protected TipoOperacao $tipoOperacao = TipoOperacao::Saida;
    protected ?IndicadorPresenca $indicadorPresenca = null;
    protected ?IndicadorIntermediador $indicadorIntermediador = null;
    protected ?string $cnpjIntermediador = null;
    protected IndicadorConsumidorFinal $consumidorFinal = IndicadorConsumidorFinal::Sim;
    protected ?Emitente $emitente = null;
    protected ?Destinatario $destinatario = null;

    /** @var list<ItemFiscal> */
    protected array $itens = [];

    /** @var list<Pagamento> */
    protected array $pagamentos = [];

    /** @var list<NfeReferenciada> */
    protected array $nfesReferenciadas = [];

    protected ?string $valorFrete = null;
    protected ?string $valorSeguro = null;
    protected ?string $outrasDespesas = null;
    protected ?string $valorDescontoDocumento = null;
    protected ?string $informacoesComplementares = null;

    public static function make(): static
    {
        return new static();
    }

    public static function nfe(): self
    {
        return new self();
    }

    public function ambiente(Ambiente $ambiente): static
    {
        $this->ambiente = $ambiente;

        return $this;
    }

    public function serie(int $serie): static
    {
        $this->serie = $serie;

        return $this;
    }

    public function naturezaOperacao(string $naturezaOperacao): static
    {
        $this->naturezaOperacao = $naturezaOperacao;

        return $this;
    }

    public function finalidade(FinalidadeNfe $finalidade): static
    {
        $this->finalidade = $finalidade;

        return $this;
    }

    public function tipoOperacao(TipoOperacao $tipoOperacao): static
    {
        $this->tipoOperacao = $tipoOperacao;

        return $this;
    }

    public function indicadorPresenca(IndicadorPresenca $indicadorPresenca): static
    {
        $this->indicadorPresenca = $indicadorPresenca;

        return $this;
    }

    /**
     * Indicador de intermediador/marketplace (NT 2020.006 — indIntermed, só NF-e 55).
     * SemIntermediador é o default da FiscalAPI; PlataformaTerceiros exige o
     * CNPJ do intermediador.
     */
    public function intermediador(IndicadorIntermediador $indicador, ?string $cnpj = null): static
    {
        if ($cnpj !== null) {
            $cnpj = Cnpj::criar($cnpj)->valor();
        }
        if ($indicador === IndicadorIntermediador::PlataformaTerceiros && $cnpj === null) {
            throw new \FiscalLib\Exceptions\ValidationException('Intermediador exige CNPJ.', [
                'cnpjIntermediador' => ['IndicadorIntermediador::PlataformaTerceiros exige o CNPJ do intermediador.'],
            ]);
        }
        $this->indicadorIntermediador = $indicador;
        $this->cnpjIntermediador = $cnpj;

        return $this;
    }

    public function consumidorFinal(IndicadorConsumidorFinal $consumidorFinal): static
    {
        $this->consumidorFinal = $consumidorFinal;

        return $this;
    }

    public function emitente(Emitente $emitente): static
    {
        $this->emitente = $emitente;

        return $this;
    }

    public function destinatario(Destinatario $destinatario): static
    {
        $this->destinatario = $destinatario;

        return $this;
    }

    public function addItem(ItemFiscal $item): static
    {
        $this->itens[] = $item;

        return $this;
    }

    public function pagamento(FormaPagamento $forma, string|int|float $valor): static
    {
        $this->pagamentos[] = new Pagamento($forma, Matematica::escalar($valor, 2));

        return $this;
    }

    public function nfeReferenciada(string $chaveAcesso): static
    {
        $this->nfesReferenciadas[] = new NfeReferenciada(ChaveAcesso::criar($chaveAcesso)->valor());

        return $this;
    }

    public function frete(string|int|float $valor): static
    {
        $this->valorFrete = Matematica::escalar($valor, 2);

        return $this;
    }

    public function seguro(string|int|float $valor): static
    {
        $this->valorSeguro = Matematica::escalar($valor, 2);

        return $this;
    }

    public function outrasDespesas(string|int|float $valor): static
    {
        $this->outrasDespesas = Matematica::escalar($valor, 2);

        return $this;
    }

    public function descontoTotal(string|int|float $valor): static
    {
        $this->valorDescontoDocumento = Matematica::escalar($valor, 2);

        return $this;
    }

    public function informacoesComplementares(string $texto): static
    {
        $this->informacoesComplementares = $texto;

        return $this;
    }

    /**
     * @throws ValidationException
     * @throws TaxInconsistencyException
     */
    public function build(): NfeDocumento
    {
        $erros = [];

        if ($this->serie < 1 || $this->serie > 999) {
            $erros['serie'][] = 'Série deve estar entre 1 e 999.';
        }
        if ($this->naturezaOperacao === null || trim($this->naturezaOperacao) === '') {
            $erros['naturezaOperacao'][] = 'Natureza da operação é obrigatória.';
        }
        if ($this->itens === []) {
            $erros['itens'][] = 'A nota deve ter ao menos um item.';
        }

        foreach ($this->itens as $i => $item) {
            $esperado = Matematica::multiplicar($item->quantidade, $item->valorUnitario, 2);
            if (! Matematica::igual($esperado, $item->valorTotal)) {
                $erros["itens[{$i}].valorTotal"][] = "Quantidade × valorUnitario ({$esperado}) difere do valorTotal ({$item->valorTotal}).";
            }

            // R001/R002 — coerência CFOP × tipo de operação.
            if ($item->cfop !== null) {
                $cfop = CodigoCfop::criar($item->cfop);
                if ($cfop->isSaida() && $this->tipoOperacao === TipoOperacao::Entrada) {
                    $erros["itens[{$i}].cfop"][] = "CFOP {$cfop} é de saída, mas tipoOperacao = entrada (R001/R002).";
                }
                if ($cfop->isEntrada() && $this->tipoOperacao === TipoOperacao::Saida) {
                    $erros["itens[{$i}].cfop"][] = "CFOP {$cfop} é de entrada, mas tipoOperacao = saída (R001/R002).";
                }
            }
        }

        if ($this->finalidade === FinalidadeNfe::Devolucao && $this->nfesReferenciadas === []) {
            $erros['nfesReferenciadas'][] = 'Devolução exige ao menos uma NF-e referenciada (v2 F4).';
        }

        foreach ($this->pagamentos as $p) {
            if (bccomp($p->valor, '0', 2) < 0) {
                $erros['pagamento'][] = 'Valor de pagamento negativo.';
            }
        }

        if ($erros !== []) {
            throw new ValidationException('Documento fiscal inválido.', $erros);
        }

        $totais = TotaisDocumento::calcular(
            $this->itens,
            $this->valorDescontoDocumento,
            $this->valorFrete,
            $this->valorSeguro,
            $this->outrasDespesas,
        );

        $this->validarEspecifico($totais);

        return new NfeDocumento(
            modelo: $this->modeloDocumento(),
            ambiente: $this->ambiente,
            serie: $this->serie,
            itens: $this->itens,
            totais: $totais,
            naturezaOperacao: $this->naturezaOperacao,
            finalidade: $this->finalidade,
            tipoOperacao: $this->tipoOperacao,
            indicadorPresenca: $this->indicadorPresenca,
            indicadorConsumidorFinal: $this->consumidorFinal,
            destinatario: $this->destinatario,
            emitente: $this->emitente,
            pagamentos: $this->pagamentos,
            nfesReferenciadas: $this->nfesReferenciadas,
            informacoesComplementares: $this->informacoesComplementares,
            indicadorIntermediador: $this->indicadorIntermediador,
            cnpjIntermediador: $this->cnpjIntermediador,
        );
    }

    protected function modeloDocumento(): ModeloDocumento
    {
        return ModeloDocumento::Nfe;
    }

    /** Ganchos de validação do NFC-e builder. */
    protected function validarEspecifico(TotaisDocumento $totais): void
    {
    }

    /** @return list<string> */
    protected static function validarPagamentosNfce(TotaisDocumento $totais, array $pagamentos): array
    {
        $erros = [];
        if ($pagamentos === []) {
            $erros['pagamento'][] = 'NFC-e exige ao menos uma forma de pagamento.';
        }

        $totalPago = '0.00';
        foreach ($pagamentos as $pagamento) {
            $totalPago = Matematica::somar($totalPago, $pagamento->valor);
        }
        if (bccomp($totalPago, $totais->valorNota, 2) === -1 && ! Matematica::igual($totalPago, $totais->valorNota)) {
            $erros['pagamento'][] = "Somatório dos pagamentos ({$totalPago}) menor que o total da nota ({$totais->valorNota}).";
        }

        return $erros;
    }

    protected static function erro(string $campo, string $mensagem): ValidationException
    {
        return new ValidationException($mensagem, [$campo => [$mensagem]]);
    }

    protected static function exigir(bool $condicao, string $campo, string $mensagem): void
    {
        if (! $condicao) {
            throw MissingFieldException::campo($campo);
        }
    }
}
