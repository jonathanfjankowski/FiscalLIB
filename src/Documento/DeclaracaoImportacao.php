<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\Matematica;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Exceptions\ValidationException;

/**
 * Declaração de Importação do item (grupo `DI` da NF-e — espelha DiDto da FiscalAPI).
 */
final class DeclaracaoImportacao
{
    public readonly ?string $valorAfrmm;
    public readonly ?string $cnpjAdquirente;
    public readonly ?string $cnpjProdutor;

    /**
     * @param list<AdicaoImportacao> $adicoes
     */
    public function __construct(
        public readonly string $numeroDi,
        public readonly string $dataRegistro,
        public readonly string $localDesembaraco,
        public readonly string $ufDesembaraco,
        public readonly string $dataDesembaraco,
        public readonly int $viaTransporte,
        public readonly int $formaIntermediacao,
        public readonly string $codigoExportador,
        string|int|float|null $valorAfrmm = null,
        ?string $cnpjAdquirente = null,
        ?string $cnpjProdutor = null,
        public readonly array $adicoes = [],
    ) {
        if (trim($numeroDi) === '' || mb_strlen($numeroDi) > 12) {
            throw ValidationException::erro('dis.numeroDi', 'numeroDi é obrigatório (máx. 12 chars).');
        }
        if (preg_match('/^[A-Z]{2}$/', strtoupper($ufDesembaraco)) !== 1) {
            throw ValidationException::erro('dis.ufDesembaraco', 'ufDesembaraco deve ter 2 letras (UF).');
        }
        if ($viaTransporte < 1 || $viaTransporte > 12) {
            throw ValidationException::erro('dis.viaTransporte', 'viaTransporte (tpViaTransp) deve estar entre 1 e 12.');
        }
        if ($formaIntermediacao < 1 || $formaIntermediacao > 4) {
            throw ValidationException::erro('dis.formaIntermediacao', 'formaIntermediacao (tpIntermedio) deve estar entre 1 e 4.');
        }
        if (trim($codigoExportador) === '' || mb_strlen($codigoExportador) > 19) {
            throw ValidationException::erro('dis.codigoExportador', 'codigoExportador é obrigatório (máx. 19 chars).');
        }
        if (count($adicoes) > 999) {
            throw ValidationException::erro('dis.adicoes', 'DI aceita no máximo 999 adições.');
        }

        $this->valorAfrmm = $valorAfrmm === null ? null : Matematica::escalar($valorAfrmm, 2);
        $this->cnpjAdquirente = $cnpjAdquirente !== null && trim($cnpjAdquirente) !== ''
            ? Cnpj::criar($cnpjAdquirente)->valor()
            : null;
        $this->cnpjProdutor = $cnpjProdutor !== null && trim($cnpjProdutor) !== ''
            ? Cnpj::criar($cnpjProdutor)->valor()
            : null;
    }
}
