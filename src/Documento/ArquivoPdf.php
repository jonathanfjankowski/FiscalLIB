<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

/**
 * PDF/DANFE baixado do emissor.
 */
final class ArquivoPdf
{
    public function __construct(
        public readonly string $conteudo,
        public readonly bool $isBase64 = false,
        public readonly string $contentType = 'application/pdf',
    ) {
    }

    /** Conteúdo binário puro (decodifica se veio em base64). */
    public function bytes(): string
    {
        return $this->isBase64 ? (string) base64_decode($this->conteudo, true) : $this->conteudo;
    }
}
