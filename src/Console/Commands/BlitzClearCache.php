<?php

namespace NickyX3\Blitz\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use NickyX3\Blitz\Support\BlitzTemplateCompiler;

class BlitzClearCache extends Command
{
    protected $signature    = 'blitz:clear';
    protected $description  = 'Clear Blitz compiled templates cache';

    /**
     * @throws FileNotFoundException
     */
    public function handle(){
        BlitzTemplateCompiler::clearCache();
    }
}


