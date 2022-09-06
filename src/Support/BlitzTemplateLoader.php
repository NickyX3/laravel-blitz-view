<?php

namespace NickyX3\Blitz\Support;

use Illuminate\Contracts\Filesystem\FileNotFoundException;

class BlitzTemplateLoader
{
    protected static string $templates_path;
    protected static array  $callbacks   = [];

    /**
     * @throws FileNotFoundException
     */
    public static function load (string $template_name):string
    {
        self::$templates_path   = ini_get('blitz.path');
        $template_file_name     = str_replace('.',\DIRECTORY_SEPARATOR,$template_name).'.tpl';
        $template_full_path     = self::$templates_path.$template_file_name;

        $template_content       = BlitzTemplateCompiler::get($template_full_path);
        self::$callbacks        = BlitzTemplateCompiler::getCallbacks($template_full_path);
        return $template_content;
    }

    public static function getCC ( string $template_name ):array
    {
        return self::$callbacks;
    }
}
