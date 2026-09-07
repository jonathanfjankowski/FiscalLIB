<?php

declare(strict_types=1);

namespace FiscalLib\Common\ValueObjects;

use FiscalLib\Exceptions\InvalidValueException;

/**
 * Chave de acesso: 44 posições (NF-e/NFC-e) ou 50 posições (NFS-e Nacional,
 * prefixo "NFS"), com validação do dígito verificador (módulo 11).
 */
final class ChaveAcesso implements \Stringable
{
    private function __construct(private readonly string $valor)
    {
    }

    public static function criar(string $valor): self
    {
        $limpo = preg_replace('/\s+/', '', $valor) ?? '';

        $tamanho = strlen($limpo);
        if ($tamanho !== 44 && $tamanho !== 50) {
            throw InvalidValueException::campo('chaveAcesso', $valor, 'deve ter 44 (NF-e/NFC-e) ou 50 (NFS-e) posições.');
        }

        if ($tamanho === 44) {
            if (! ctype_digit($limpo)) {
                throw InvalidValueException::campo('chaveAcesso', $valor, 'NF-e/NFC-e: somente dígitos.');
            }

            $dvInformado = (int) substr($limpo, -1);
            $dvCalculado = self::dvModulo11(substr($limpo, 0, -1));
            if ($dvInformado !== $dvCalculado) {
                throw InvalidValueException::campo('chaveAcesso', $valor, "dígito verificador inválido (esperado {$dvCalculado}).");
            }

            return new self($limpo);
        }

        // NFS-e Nacional (50 posições, prefixo "NFS"): charset alfanumérico —
        // o DV alfanumérico segue a tabela de conversão da NT e não é conferido aqui.
        if (! str_starts_with($limpo, 'NFS') || ! preg_match('/^NFS[A-Z0-9]{47}$/', $limpo)) {
            throw InvalidValueException::campo('chaveAcesso', $valor, 'NFS-e Nacional deve iniciar com "NFS" e ter 50 posições alfanuméricas.');
        }

        return new self($limpo);
    }

    public static function valido(string $valor): bool
    {
        try {
            self::criar($valor);

            return true;
        } catch (InvalidValueException) {
            return false;
        }
    }

    /**
     * DV módulo 11 com pesos 2–9 da direita para a esquerda.
     * Resto 0 ou 1 → DV = 0.
     */
    public static function dvModulo11(string $corpo): int
    {
        $peso = 2;
        $soma = 0;
        $caracteres = str_split(strrev($corpo));
        foreach ($caracteres as $digito) {
            $soma += ((int) $digito) * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }
        $resto = $soma % 11;

        return ($resto === 0 || $resto === 1) ? 0 : 11 - $resto;
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function isNfse(): bool
    {
        return strlen($this->valor) === 50;
    }

    public function ufCodigo(): string
    {
        return substr($this->valor, 0, 2);
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
