<?php

namespace NickyX3\Blitz\Support\Cache;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Redis;

class BlitzTemplateCacheRedis implements BlitzTemplateCacheInterface
{

    protected string                $source_file_path;
    protected string                $compiled_file_path;
    protected array                 $compiled;
    protected static string         $compiled_folder;
    protected static string         $compiled_path;
    protected static string         $parent_separator = '<!-- ### PARENTS ### -->';

    public function __construct(string $template_file_path)
    {
        self::$compiled_folder      = config('blitz.compiled_folder','blitz_compiled');
        self::$compiled_path        = storage_path() . '/' . self::$compiled_folder . '/';

        $this->source_file_path     = $template_file_path;
        $this->compiled_file_path   = self::$compiled_path . md5($this->source_file_path).'.tpl';

        $this->compiled             = [];
    }

    public function isExist(): bool
    {
        $exists = Redis::exists($this->compiled_file_path);

        if ( $exists !== 0 ) {
            return true;
        } else {
            return false;
        }
    }

    public function getTemplateContent(): string|false
    {
        $cachedData     = $this->getFromCache();
        if ( $cachedData !== false ) {
            $this->compiled = $cachedData;
            $cache_valid    = $this->reValidateCache($this->compiled['parents']);
            if ($cache_valid) {
                return $this->compiled['content'];
            }
        }
        // delete cached key
        self::deleteKeyInCache($this->compiled_file_path);
        return false;
    }

    public function setTemplateCache(string $content, array $templates_tree=[]): int|bool
    {
        $dataToStore = ['content'=>$content,'mTime'=>time()];
        if ( isset($templates_tree[$this->source_file_path]['parents']) ) {
            $dataToStore['parents'] = implode(self::$parent_separator,$templates_tree[$this->source_file_path]['parents']);
        }
        return Redis::hMSet($this->compiled_file_path,$dataToStore);
    }

    protected function getFromCache ():array|false
    {
        $cachedData = self::getKeyData($this->compiled_file_path);
        if ( $cachedData !== false ) {
            $result = $cachedData;
            if ( isset($cachedData['parents']) ) {
                $parents = explode(self::$parent_separator,$cachedData['parents']);
                if ( count($parents) > 0 ) {
                    $result['parents'] = $parents;
                } else {
                    $result['parents'] = [];
                }
            } else {
                $result['parents'] = [];
            }
            return $result;
        } else {
            return false;
        }
    }

    protected function reValidateCache (array $filesToCheck=[]):bool
    {
        $filesToCheck[]  = $this->source_file_path;
        if ( count($filesToCheck) > 0 ) {
            foreach ($filesToCheck as $fileToCheck) {
                $checkFile = new File($fileToCheck, false);
                if ( $checkFile->isFile() && $checkFile->getMTime() > $this->compiled['mTime'] ){
                    return false;
                }
            }
        }
        return true;
    }

    protected static function getKeyData (string $key):array|false
    {
        $exists = Redis::exists($key);
        if ( $exists !== 0 ) {
            return Redis::hGetAll($key);
        } else {
            return false;
        }
    }

    public static function clearCache(): void
    {
        if ( !isset(self::$compiled_path) || is_null(self::$compiled_path) ) {
            self::$compiled_folder = config('blitz.compiled_folder', 'blitz_compiled');
            self::$compiled_path = storage_path() . '/' . self::$compiled_folder . '/';
        }

        $all_cached_keys  = Redis::keys(self::$compiled_path.'*');
        if ( count($all_cached_keys) > 0 ) {
            foreach ($all_cached_keys as $all_cached_key) {
                self::deleteKeyInCache($all_cached_key);
            }
        }
    }

    protected static function deleteKeyInCache (string $key):void
    {
        Redis::del($key);
    }

}
