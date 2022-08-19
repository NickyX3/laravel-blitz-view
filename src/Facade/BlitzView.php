<?php

namespace NickyX3\Blitz\Facade;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Facade;

use NickyX3\Blitz\BlitzMaker;

/**
 * @method static Response apply(string $template, array $data)
 *
 * @see BlitzMaker
 */
class BlitzView extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'blitz';
    }
}
