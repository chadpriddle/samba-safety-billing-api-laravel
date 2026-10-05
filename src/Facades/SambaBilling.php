<?php

namespace ChadPriddle\SambaBilling\Facades;

use Illuminate\Support\Facades\Facade;

class SambaBilling extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'samba-billing';
    }
}
