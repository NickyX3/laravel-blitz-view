<?php

namespace NickyX3\Blitz\Support\Cache;

interface BlitzTemplateCacheInterface
{
    public function __construct(string $template_file_path);
    public function isExist():bool;
    public function getTemplateContent():string|false;
    public function setTemplateCache(string $content):int|bool;
    public static function clearCache():void;
}
