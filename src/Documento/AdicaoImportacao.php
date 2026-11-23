<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\Matematica;
use FiscalLib\Exceptions\ValidationException;

/**
 * Adição da Declaração de Importação (grupo `adi` da NF-e — espelha DiAdicaoDto).
 */
final class AdicaoImportacao
{
    public readonly ?string $valorDescontoDi;

    public function __construct(
        public readonly int $numeroAdicao,
        public readonly int $sequencial,
        public readonly string $codigoFabricante,
        string|int|float|null $valorDescontoDi = null,
        public readonly ?string $numeroDrawback = null,
    ) {
        if ($numeroAdicao < 1) {
            throw ValidationException::erro('dis.adicoes.numeroAdicao', 'numeroAdicao deve ser maior que zero.');
        }
        if ($sequencial < 1) {
            throw ValidationException::erro('dis.adicoes.sequencial', 'sequencial deve ser maior que zero.');
        }
        if (trim($codigoFabricante) === '' || mb_strlen($codigoFabricante) > 20) {
            throw ValidationException::erro('dis.adicoes.codigoFabricante', 'codigoFabricante é obrigatório (máx. 20 chars).');
        }
        if ($numeroDrawback !== null && mb_strlen($numeroDrawback) > 11) {
            throw ValidationException::erro('dis.adicoes.numeroDrawback', 'numeroDrawback deve ter no máximo 11 caracteres.');
        }

        $this->valorDescontoDi = $valorDescontoDi === null ? null : Matematica::escalar($valorDescontoDi, 2);
    }
}
