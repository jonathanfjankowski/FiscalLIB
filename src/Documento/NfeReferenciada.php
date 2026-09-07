<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

/**
 * NF-e referenciada (grupo NFref) — obrigatória na devolução (v2 F4).
 */
final class NfeReferenciada
{
    public function __construct(public readonly string $chaveAcesso)
    {
    }
}
