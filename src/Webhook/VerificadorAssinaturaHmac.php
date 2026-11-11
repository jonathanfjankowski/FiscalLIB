<?php

declare(strict_types=1);

namespace FiscalLib\Webhook;

use FiscalLib\Exceptions\FiscalLibException;

/**
 * Valida a assinatura HMAC-SHA256 das entregas de webhook da FiscalAPI:
 * header `X-Fiscal-Signature: sha256=<hex>` sobre "{timestamp}.{corpo}",
 * com janela anti-replay de 5 minutos.
 *
 * O timestamp pode vir de DUAS formas (as duas existem no mundo):
 *  - dentro do header de assinatura (`t=<unix>,v1=<hex>` — formato do
 *    helper `header()` desta lib);
 *  - no header dedicado `X-Fiscal-Timestamp` (formato que a FiscalAPI
 *    envia: `X-Fiscal-Signature: sha256=<hex>` + `X-Fiscal-Timestamp`).
 *
 * Uso no ERP (endpoint que recebe o webhook):
 *   VerificadorAssinaturaHmac::validar(
 *       $corpoBruto,
 *       $_SERVER['HTTP_X_FISCAL_SIGNATURE'],
 *       $secret,
 *       timestampHeader: $_SERVER['HTTP_X_FISCAL_TIMESTAMP'] ?? null,
 *   );
 */
final class VerificadorAssinaturaHmac
{
    public const JANELA_PADRAO_SEGUNDOS = 300;

    /**
     * @throws FiscalLibException quando ausente/malformada/expirada/inválida.
     */
    public static function validar(
        string $corpo,
        ?string $assinaturaHeader,
        string $segredo,
        int $janelaSegundos = self::JANELA_PADRAO_SEGUNDOS,
        ?int $agora = null,
        ?string $timestampHeader = null,
    ): void {
        if ($assinaturaHeader === null || trim($assinaturaHeader) === '') {
            throw new FiscalLibException('Webhook sem assinatura (X-Fiscal-Signature).');
        }

        $partes = explode(',', $assinaturaHeader);
        $timestamp = null;
        $hashInformado = null;
        foreach ($partes as $parte) {
            $chaveValor = explode('=', trim($parte), 2);
            if (count($chaveValor) === 2) {
                [$chave, $valor] = $chaveValor;
                $valor = strtolower(trim($valor));
                if ($chave === 't') {
                    $timestamp = $valor;
                } elseif ($chave === 'v1' || $chave === 'sha256') {
                    $hashInformado = $valor;
                }
            }
        }

        // Formato da FiscalAPI: o timestamp vem no header dedicado
        // (X-Fiscal-Timestamp) e a assinatura é só "sha256=<hex>".
        if ($timestamp === null && $timestampHeader !== null) {
            $timestampDedicated = trim($timestampHeader);
            if (ctype_digit($timestampDedicated)) {
                $timestamp = $timestampDedicated;
            }
        }

        if ($timestamp === null || $hashInformado === null || ! ctype_digit($timestamp)) {
            throw new FiscalLibException('Assinatura de webhook malformada.');
        }

        $agora = $agora ?? time();
        if (abs($agora - (int) $timestamp) > $janelaSegundos) {
            throw new FiscalLibException('Assinatura de webhook fora da janela anti-replay.');
        }

        $esperado = self::calcular($corpo, $timestamp, $segredo);
        if (! hash_equals($esperado, $hashInformado)) {
            throw new FiscalLibException('Assinatura de webhook inválida.');
        }
    }

    /** Gera o valor esperado do header (útil para testes do ERP). */
    public static function calcular(string $corpo, string $timestamp, string $segredo): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $corpo, $segredo);
    }

    /** Monta um header de assinatura (útil para testes). */
    public static function header(string $corpo, string $segredo, ?int $agora = null): string
    {
        $timestamp = (string) ($agora ?? time());

        return "t={$timestamp},v1=" . self::calcular($corpo, $timestamp, $segredo);
    }
}
