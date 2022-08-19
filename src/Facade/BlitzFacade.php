<?php

namespace NickyX3\Blitz\Facade;
use NickyX3\Blitz\BlitzView;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Response apply(string $template, array $data)
 *
 * @see BlitzView
 */
class BlitzFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'blitz';
    }
}
