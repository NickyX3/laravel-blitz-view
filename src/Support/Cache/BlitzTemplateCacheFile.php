<?php

namespace NickyX3\Blitz\Support\Cache;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\File;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class BlitzTemplateCacheFile implements BlitzTemplateCacheInterface
{

    protected string                $source_file_path;
    protected string                $compiled_file_path;
    protected File                  $compiled;
    protected static string         $compiled_folder;
    protected static string         $compiled_path;
    protected static array          $callbacks          = [];
    protected static string         $parts_separator    = '<!-- ### PARTS ### -->';
    protected static string         $parent_separator   = '<!-- ### PARENTS ### -->';

    public function __construct(string $template_file_path)
    {
        self::$compiled_folder      = config('blitz.compiled_folder','blitz_compiled');
        self::$compiled_path        = storage_path() . '/' . self::$compiled_folder . '/';

        $this->source_file_path     = $template_file_path;
        $this->compiled_file_path   = self::$compiled_path . md5($this->source_file_path).'.tpl';
        $this->compiled             = new File($this->compiled_file_path,false);
    }

    public function isExist(): bool
    {
        return $this->compiled->isFile();
    }

    public function getTemplateContent(): string|false
    {
        $cachedData = $this->getFromCache();
        if ( $cachedData !== false ) {
            $cache_valid = $this->reValidateCache($cachedData['parents']);
            if ($cache_valid) {
                if ( isset($cachedData['callbacks']) ) {
                    self::$callbacks = $cachedData['callbacks'];
                }
                return $cachedData['content'];
            }
        }
        // delete cached file
        self::deleteFileInCache($this->compiled);
        return false;
    }

    public function setTemplateCache(string $content, array $templates_tree=[],array $callbacks=[]): int|bool
    {
        if ( $this->makeTargetDirectory($this->compiled) ) {
            if ( isset($templates_tree[$this->source_file_path]['parents']) ) {
                $content = $content.self::$parts_separator.implode(self::$parent_separator,$templates_tree[$this->source_file_path]['parents']);
                $content = $content.self::$parts_separator.json_encode($callbacks);
            }
            return file_put_contents($this->compiled_file_path,$content);
        } else {
            return false;
        }
    }

    public function getCallbacks ():array
    {
        return self::$callbacks;
    }

    protected function getFromCache ():array|false
    {
        if ( $this->isExist() ) {
            $result              = [];
            $cached_file_content = $this->compiled->getContent();
            $exploded            = explode(self::$parts_separator, $cached_file_content, 3);
            $result['content']   = $exploded[0];
            if ( isset($exploded[1]) ) {
                $parents = explode(self::$parent_separator,$exploded[1]);
                if ( count($parents) > 0 ) {
                    $result['parents'] = $parents;
                } else {
                    $result['parents'] = [];
                }
            } else {
                $result['parents'] = [];
            }
            if ( isset($exploded[2]) && $callbacks = json_decode($exploded[2],true) ) {
                $result['callbacks']    = $callbacks;
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
                if ($checkFile->isFile() && $checkFile->getMTime() > $this->compiled->getMTime()) {
                    return false;
                }
            }
        }
        return true;
    }

    protected function makeTargetDirectory (File $file):bool
    {
        $target_directory = $this->compiled->getPath();
        if (!is_dir($target_directory)) {
            if (false === @mkdir($target_directory, 0777, true) && !is_dir($target_directory)) {
                throw new FileException(sprintf('Unable to create the "%s" directory.', $target_directory));
            }
        } elseif (!is_writable($target_directory)) {
            throw new FileException(sprintf('Unable to write in the "%s" directory.', $target_directory));
        }
        return true;
    }

    /**
     * @throws FileNotFoundException
     */
    public static function clearCache():void
    {
        if ( !isset(self::$compiled_path) || is_null(self::$compiled_path) ) {
            self::$compiled_folder  = config('blitz.compiled_folder', 'blitz_compiled');
            self::$compiled_path    = storage_path() . '/' . self::$compiled_folder . '/';
        }
        if ( file_exists(self::$compiled_path) ) {
            $directory = new \DirectoryIterator(rtrim(self::$compiled_path, DIRECTORY_SEPARATOR));
            if ($directory->isDir() && $directory->isWritable()) {
                foreach ($directory as $item) {
                    $file_name = $item->getFilename();
                    if (!in_array($file_name, ['.', '..'])) {
                        self::deleteFileInCache($item);
                    }
                }
            } else {
                throw new FileNotFoundException('Path ' . $directory->getFilename() . ' is not Dir or is not Writable');
            }
        }
    }

    protected static function deleteFileInCache (\SplFileInfo $fileToDelete):void
    {
        if ( $fileToDelete->isFile() && $fileToDelete->isWritable() ) {
            $full_name = $fileToDelete->getPathname();
            if (false === @unlink($full_name)) {
                throw new FileException(sprintf('Unable to delete file "%s".', $full_name));
            }
        }
    }
}
