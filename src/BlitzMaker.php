<?php

namespace NickyX3\Blitz;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Response;

use NickyX3\Blitz\Exceptions\BlitzException;
use NickyX3\Blitz\Exceptions\BlitzHandlerException;
use NickyX3\Blitz\Support\BlitzTemplateLoader;

class BlitzMaker
{
    protected string            $templates_path;
    protected string            $templates_folder       = 'blitz_view';
    protected int               $scope_limit            = 8;
    protected int               $php_callbacks_first    = 1;
    protected static string     $template_content       = '';
    protected static string     $template_name          = '';
    protected static array      $data                   = [];
    protected static string     $rendered               = '';
    protected static Response   $response;
    protected \Blitz            $blitzObject;

    /**
     * @throws BlitzException|FileNotFoundException
     */
    public function __construct(string|null $template=null,array|null $data=null)
    {
        if ( extension_loaded('blitz')) {
            $this->scope_limit          = config('blitz.scope_lookup_limit',$this->scope_limit);
            $this->php_callbacks_first  = config('blitz.php_callbacks_first',$this->php_callbacks_first);
            $this->templates_folder     = config('blitz.templates_folder',$this->templates_folder);

            $this->templates_path       = resource_path().'/'.$this->templates_folder.'/';

            ini_set('blitz.path', $this->templates_path);
            ini_set('blitz.scope_lookup_limit',$this->scope_limit);
            ini_set('blitz.php_callbacks_first', $this->php_callbacks_first);

            if ( !is_null($template) && !is_null($data) ) {
                self::$template_name  = $template;
                self::$data           = $data;
                self::$response       = $this->apply(self::$template_name,self::$data);
            }
        } else {
            throw new BlitzHandlerException('Blitz Extension Not Loaded');
        }
    }

    /**
     * @throws FileNotFoundException
     * @throws BlitzException
     */
    public function apply(string $template, array $data = []):Response
    {
        self::$template_name        = $template;
        self::$data                 = $data;
        self::$template_content     = BlitzTemplateLoader::load(self::$template_name);

        self::injectConditionCallbacks();

        $callbackException = function ($error_code, $error_message) {
            $template_content   = BlitzMaker::getTemplateContent();
            $template_name      = BlitzMaker::getTemplateName();
            $template_data      = json_encode(BlitzMaker::getData());
            $separator = PHP_EOL.'<!-- SEPARATOR -->'.PHP_EOL;
            throw new BlitzHandlerException($error_message.$separator.$template_name.$separator.$template_data.$separator.$template_content, $error_code);
        };

        set_error_handler($callbackException, E_ERROR|E_WARNING);
        $this->blitzObject  = new \Blitz();
        $this->blitzObject->load(self::$template_content);
        $parsed_content = $this->blitzObject->parse(self::$data);
        self::$rendered = $parsed_content;
        restore_error_handler();

        self::$response = new Response(self::$rendered);

        return self::$response;
    }

    /**
     * @throws BlitzException
     * @throws FileNotFoundException
     * @alias  apply()
     */
    public function make(string $template, array $data = []):Response
    {
        return $this->apply($template,$data);
    }

    public function getBlitzObj():\Blitz
    {
        return $this->blitzObject;
    }

    public static function getData ():array
    {
        return self::$data;
    }
    public static function getTemplateContent():string
    {
        return self::$template_content;
    }
    public static function getTemplateName():string
    {
        return str_replace('.',DIRECTORY_SEPARATOR,self::$template_name).'.tpl';
    }

    public function __toString(): string
    {
        return self::$response->getContent();
    }

    public function __call($name, $arguments) {
        dump ("Method call '$name' " . implode(', ', $arguments));
    }

    public static function __callStatic($name, $arguments) {
        dump ("Static method call '$name' " . implode(', ', $arguments));
    }

    protected static function injectConditionCallbacks ():void
    {
        $callbacks = BlitzTemplateLoader::getCC(self::$template_name);
        foreach ($callbacks as $var_name=>$callback_code) {
            self::$template_content = str_replace($callback_code,'$callback_'.$var_name,self::$template_content);
            self::$data['callback_'.$var_name] =  eval("return $callback_code;");
        }
    }
}
