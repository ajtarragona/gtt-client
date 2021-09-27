<?php

namespace Ajtarragona\GTT\Facades; 

use Illuminate\Support\Facades\Facade;

class GTT extends Facade
{
    /**
     * {@inheritdoc}
     */
    protected static function getFacadeAccessor()
    {
        return 'gtt';
    }
}
