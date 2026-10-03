<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Forma de pagamento (tPag — tabela SEFAZ).
 */
enum FormaPagamento: string
{
    case Dinheiro = '01';
    case Cheque = '02';
    case CartaoCredito = '03';
    case CartaoDebito = '04';
    case CreditoLoja = '05';
    case ValeAlimentacao = '10';
    case ValeRefeicao = '11';
    case ValePresente = '12';
    case ValeCombustivel = '13';
    case DuplicataMercantil = '14';
    case Boleto = '15';
    case DepositoBancario = '16';
    case PagamentoInstantaneoPix = '17';
    case TransferenciaBancariaCarteiraDigital = '18';
    case FidelidadeCashbackCreditoVirtual = '19';
    case PagamentoPosterior = '21';
    case SemPagamento = '90';
    case Outros = '99';
}
