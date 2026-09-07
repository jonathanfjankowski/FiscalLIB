<?php

declare(strict_types=1);

namespace FiscalLib\Laravel;

use FiscalLib\FiscalLib;

/**
 * Facade opcional do Laravel: FiscalLibFacade::nfe()->emitir($documento).
 */
final class FiscalLibFacade extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'fiscal-lib';
    }

    public static function lib(): FiscalLib
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }
}
