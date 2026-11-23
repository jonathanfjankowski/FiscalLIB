<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Exceptions\ValidationException;

/**
 * Dados de transação de cartão/Pix integrado (grupo `card` da SEFAZ — NF-e/NFC-e).
 * Espelha os campos de cartão do PagamentoDto da FiscalAPI:
 * tipoIntegracao ("1" integrado / "2" não integrado), bandeira (tBand, 2 dígitos),
 * autorizacao (cAut, até 20 chars) e cnpjCredenciadora (opcional, 14 dígitos).
 */
final class DadosCartao
{
    public readonly ?string $cnpjCredenciadora;

    public function __construct(
        public readonly string $tipoIntegracao,
        public readonly string $bandeira,
        public readonly string $autorizacao,
        ?string $cnpjCredenciadora = null,
    ) {
        if (! in_array($tipoIntegracao, ['1', '2'], true)) {
            throw ValidationException::erro('pagamento.tipoIntegracao', "tipoIntegracao deve ser '1' (integrado) ou '2' (não integrado).");
        }
        if (preg_match('/^\d{2}$/', $bandeira) !== 1) {
            throw ValidationException::erro('pagamento.bandeira', 'bandeira (tBand) deve ter exatamente 2 dígitos numéricos.');
        }
        if (trim($autorizacao) === '' || mb_strlen($autorizacao) > 20) {
            throw ValidationException::erro('pagamento.autorizacao', 'autorizacao (cAut) é obrigatória e deve ter no máximo 20 caracteres.');
        }

        $this->cnpjCredenciadora = $cnpjCredenciadora !== null && trim($cnpjCredenciadora) !== ''
            ? Cnpj::criar($cnpjCredenciadora)->valor()
            : null;
    }
}
