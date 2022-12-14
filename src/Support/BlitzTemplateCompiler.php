<?php

namespace NickyX3\Blitz\Support;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\File;

class BlitzTemplateCompiler
{
    protected static string $templates_folder;
    protected static string $templates_path;
    protected static string $current_file;
    protected static array  $templates_tree;
    protected static array  $templates;
    protected static array  $namespace_finder;
    protected static array  $callableHelpers      = [];
    protected static string $cacheClass;
    protected static bool   $cacheEnabled;
    protected static array  $statistic  = ['from_cache'=>0,'from_disk'=>0,'gets'=>0,'compiled'=>0];

    /**
     * @throws FileNotFoundException
     */
    public static function get(string $template_file_path): string
    {
        self::$templates_folder = config('blitz.templates_folder','blitz_view');
        self::$namespace_finder = config('blitz.namespace_finder',[]);
        self::$cacheEnabled     = config('blitz.cache_enabled',false);
        self::$templates_path   = resource_path() . '/' . self::$templates_folder . '/';

        self::$current_file     = $template_file_path;

        if ( self::$cacheEnabled === true ) {
            // cache enabled
            self::$cacheClass   = self::getCacheClass();
            $compiled           = new self::$cacheClass(self::$current_file);
            $cached_template    = $compiled->getTemplateContent();
            // in cache
            if ( $cached_template !== false ) {
                // increase statistic
                self::$statistic['from_cache']++;
                // callbacks set
                self::$callableHelpers[self::$current_file] = $compiled->getCallbacks();
                // process helpers for autoload
                $cached_template    = self::processHelpers($cached_template);
                // return template
                return $cached_template;
            }
        }
        // source file
        $source     = new File(self::$current_file, false);
        // increase statistic
        self::$statistic['compiled']++;
        // recompile source
        $compiled_template = self::getCompiled($source);
        // check cache enabled
        if ( self::$cacheEnabled === true ) {
            // get callbacks
            $callbacks  = self::getCallbacks(self::$current_file);
            // set ti cache
            $compiled->setTemplateCache($compiled_template, self::$templates_tree, $callbacks);
        }
        return $compiled_template;
    }

    public static function getCallbacks (string $template_file_path):array {
        return self::$callableHelpers[$template_file_path];
    }

    public static function getStatistic ():array
    {
        return self::$statistic;
    }

    /**
     * @throws FileNotFoundException
     */
    public static function clearCache():void
    {
        $cacheClass  = self::getCacheClass();
        $cacheClass::clearCache();
    }

    /**
     * @throws FileNotFoundException
     */
    protected static function getCompiled (File $source):string
    {
        if ($source->isFile()) {
            // compile
            return self::compile($source);
        } else {
            throw new FileNotFoundException('Source template ' . $source->getFilename() . ' not found');
        }
    }

    protected static function compile(File $source): string
    {
        $templates_key = $source->getPathname();
        self::getExtendsThree($templates_key);
        $template_content = self::makeTemplate($templates_key);
        $template_content = self::processMultipleEOL($template_content);
        return              self::processHelpers($template_content);
    }

    protected static function getTemplateData(string $template_file_path): array
    {
        self::$statistic['gets']++;
        if ( isset(self::$templates[$template_file_path]) ) {
            return self::$templates[$template_file_path];
        } else {
            self::$statistic['from_disk']++;
            $source             = new File($template_file_path, false);
            $template_content   = $source->getContent();
            $sections           = self::getSections($template_content);
            $yelds              = self::getYelds($template_content);
            $parent_file_path   = self::getExtend($template_content);
            return [
                'name'        => $template_file_path,
                'content'     => $template_content,
                'sections'    => $sections,
                'yelds'       => $yelds,
                'parent_file' => $parent_file_path
            ];
        }
    }

    protected static function setTemplateData(string $template_file_path, array $data):array
    {
        return self::$templates[$template_file_path] = $data;
    }

    protected static function getExtendsThree (string $template_file_path):array
    {
        $template                               = self::getTemplateData($template_file_path);
        self::$templates[$template_file_path]   = $template;
        if ( !isset(self::$templates_tree) ) {
            self::$templates_tree = [];
        }

        if ( $template['parent_file'] !== false ) {
            self::$templates_tree[$template_file_path]['parents'][]         = $template['parent_file'];
            self::$templates_tree[$template['parent_file']]['childs'][]     = $template_file_path;
            if ( isset(self::$templates_tree[$template_file_path]['childs']) ) {
                foreach (self::$templates_tree[$template_file_path]['childs'] as $child) {
                    self::$templates_tree[$child]['parents'][]                  = $template['parent_file'];
                    self::$templates_tree[$template['parent_file']]['childs'][] = $child;
                }
            }
            self::$templates_tree = self::getExtendsThree($template['parent_file']);
        }

        return self::$templates_tree;
    }

    protected static function getExtend (string $content ):string|false
    {
        $pattern_extend = "/<!\-\- @extends\([\"']([^\"']+)[\"']\) \-\->/";
        $match_extend   = [];
        preg_match($pattern_extend,$content,$match_extend);
        if ( count($match_extend) == 2 ) {
            $parent_template_name   = $match_extend[1];
            $parent_template_file   = str_replace('.',\DIRECTORY_SEPARATOR,$parent_template_name).'.tpl';
            return self::$templates_path.$parent_template_file;
        }
        return false;
    }

    protected static function makeTemplate (string $template_key):string
    {
        if (isset(self::$templates_tree[$template_key]) && isset(self::$templates_tree[$template_key]['parents']) ) {
            // ???????? ????????????????
            $template       = self::getTemplateData($template_key);
            $parents        = self::$templates_tree[$template_key]['parents'];
            foreach ($parents as $parent_key) {
                $parent_template = self::getTemplateData($parent_key);
                // ?????????????????????? ???? ???????????? ????????????????
                if (isset($parent_template['yelds'])) {
                    // ???????????????????? ?????????? ????????????????
                    foreach ($parent_template['yelds'] as $yeld_name=>$yeld_code) {
                        // ?????????????????? ???????? ???? ???????????? ?????? ?????????? ??????????
                        if( isset($template['sections'][$yeld_name]) ) {
                            // ???????????? ?????? ?????????? ????????, ???????????????? ?? ???????? ????????????
                            $from = $yeld_code;
                            $to   = $template['sections'][$yeld_name]['content'];
                            $to   = str_replace('<!-- @parent -->','',$to);
                            // ???????????? ?? ???????????????? ????????????????
                            $parent_template['content']     = str_replace($from,$to,$parent_template['content']);
                            // ???????????? ?? ???????????????? ????????????????
                            if ( $parent_template['sections'] !== false ) {
                                $parent_template['sections'] = self::replaceInSections($parent_template['sections'], $from, $to);
                            }
                        }
                    }
                }

                // ???????????????????????????? ????????????????
                $parent_template = self::setTemplateData($parent_key,$parent_template);

                // ???????????????????? @parent ?? ????????????
                foreach ($template['sections'] as $section_name=>$section) {
                    // ?????????????????? ???????? ???? ?? ???????????????? ?????????? ????????????
                    if ( isset($parent_template['sections'][$section_name]) ) {
                        // ?????????????????? ???????? ?? ???????????? ???????? ???????????? ???? parent
                        if ( $section['hasParent'] === true ) {
                            // ???????????? @parent ???? ???????????? ????????????????
                            $template['sections'][$section_name]['content'] = str_replace('<!-- @parent -->',$parent_template['sections'][$section_name]['content'],$section['content']);
                        }
                        // ???????????? ???????????? ???????????????? ???? ??????????????
                        $from   = $parent_template['sections'][$section_name]['content'];
                        $to     = $template['sections'][$section_name]['content'];
                        // ???????????? ?? ???????????????? ????????????????
                        $parent_template['content'] = str_replace($from,$to,$parent_template['content']);
                        // ???????????? ?? ???????????? ????????????????
                        $parent_template['sections'][$section_name]['content']  = $to;
                    } else {
                        // ?? ???????????????? ?????? ???????? ????????????, ?????????????????? ?????? ???????????????? ????????
                        if ( $parent_template['sections'] === false ) {
                            $parent_template['sections'] = [];
                            $parent_template['sections'][$section_name] = $section;
                        }

                    }
                }
                // ???????????????????????????? ???????????? ?? ??????????????
                $template = self::setTemplateData($template_key,$template);
                // ???????????????????????????? ????????????????
                $parent_template = self::setTemplateData($parent_key,$parent_template);

                if ( $parent_template['parent_file'] === false ) {
                    $template = $parent_template;
                    break;
                }
            }
        } else {
            // ?????? ?????????????????? ?????? ?????? ?? ?????????????? ????????????????
            $template       = self::getTemplateData($template_key);
        }
        return $template['content'];
    }

    protected static function replaceInSections (array $sections, string $from, string $to):array
    {
        foreach ($sections as $section_name=>$section) {
            $sections[$section_name]['content'] = str_replace($from,$to,$section['content']);
        }
        return $sections;
    }

    protected static function getSections ( string $content ):array|false
    {
        $pattern_sections = "/<!\-\- @section\([\"']([^\"']+)[\"']\) \-\->(.*)<!\-\- @endsection \-\->/iusU";
        $match_sections   = [];
        preg_match_all($pattern_sections,$content,$match_sections);
        if ( count($match_sections[1]) > 0 ) {
            $sections = [];
            foreach ($match_sections[1] as $ind=>$match_section) {
                $sections_name      = $match_section;
                $section_content    = $match_sections[2][$ind];
                $sections[$sections_name]   = [
                    'name'      => $sections_name,
                    'content'   => $section_content,
                    'hasParent' => self::hasParentInSection($section_content)
                ];
            }
            return $sections;
        }
        return false;
    }

    protected static function getYelds (string $content):array|false
    {
        $pattern_yelds = "/<!\-\- @yield\([\"']([^\"']+)[\"']\) \-\->/iusU";
        $match_yelds   = [];
        preg_match_all($pattern_yelds,$content,$match_yelds);
        if ( count($match_yelds[1]) > 0 ) {
            $yelds = [];
            foreach ($match_yelds[1] as $ind=>$match_yeld) {
                $yelds[$match_yeld] = $match_yelds[0][$ind];
            }
            return $yelds;
        }
        return false;
    }

    protected static function hasParentInSection (string $content):bool
    {
        return mb_strpos($content, '<!-- @parent -->') !== false;
    }

    protected static function processIncludes (string $content):string
    {
        return preg_replace_callback("/\{\{ include\([\"']([^\"']+)[\"']\) \}\}/",'self::replace_include',$content);
    }

    /**
     * @throws FileNotFoundException
     */
    protected static function replace_include (array $match ):string {
        if ( count($match) === 2 ) {
            $from               = $match[0];
            $template_file      = $match[1];
            $template_file_path = self::$templates_path.$template_file;
            return self::get($template_file_path);
        }
        return '';
    }

    protected static function processGrouppedCallbacks (string $content):string
    {
        $pattern = "/\{\{\s((?:[^\(\)\s]+\(\)[^\}]+)+)\s\}\}/umU";
        return preg_replace_callback($pattern,'self::replace_groupped_callback',$content);
    }

    protected static function replace_groupped_callback ( array $match):string
    {
        $output = '';
        if ( count($match) === 2 ) {
            $output     = $match[0];
            $fullCode   = $match[1];
            $callback_pattern = "/([^:\s]+::\w+\([^\)]*\))/umU";
            preg_match_all($callback_pattern, $match[0], $cmatches);
            if (count($cmatches[0]) > 0) {
                foreach ($cmatches[0] as $callable) {
                    [$class, $method] = explode('::', $callable, 2);
                    if (!in_array($class, ['php', 'this'])) {
                        $qualifiedClass = self::getFullQualifiedClass($class);
                        if ($qualifiedClass !== '') {
                            $qualifiedCode  = $qualifiedClass . '::' . $method;
                            $fullCode       = str_replace($callable, $qualifiedCode, $fullCode);
                        }
                    }
                }
                $code       = '('.$fullCode.')';
                $output     = str_replace( $match[1],$code,$output);
                self::addToCallableStack($code);
            }
        }

        return $output;
    }

    protected static function processConditionCallbacks (string $content):string {
        $pattern = "/\{\{\sIF\s([^:\s]+::[^\(]+\([^\)]*\))[^\}]+\}\}/umU";
        return preg_replace_callback($pattern,'self::replace_condition_callback',$content);
    }

    protected static function replace_condition_callback (array $match ):string {
        $output = '';
        if ( count($match) === 2 ) {
            $where_replace  = $match[0];
            $callable       = $match[1];
            [$class,$method]    = explode('::',$callable,2);
            if ( !in_array($class,['php','this']) ) {
                $qualifiedClass = self::getFullQualifiedClass($class);
                if ($qualifiedClass !== '') {
                    $code       = $qualifiedClass . '::' . $method;
                    $output     = str_replace($callable, $code, $where_replace);
                    self::addToCallableStack($code);
                }
            } else {
                $output = str_replace($class . '::' . $method, $class . '::' . $method, $where_replace);
            }
        }
        return $output;
    }

    protected static function processHelpers (string $content):string
    {
        $content_processed  = $content;
        $content_processed  = self::processGrouppedCallbacks($content_processed);
        $content_processed  = self::processConditionCallbacks($content_processed);
        // patterns
        $pattern_single_helpers                 = "/\{\{\s([^:\s,]+::[^\(]+\([^\)]*\))\)*\s\}\}/umU";
        $pattern_conditions                     = "/\{\{\s([^\?]+)\?+\s([^:\s,]+::[^\(]+\([^\)]*\))\)*\s\}\}/umU";
        $pattern_conditions_inline_with_method  = "/\{\{\sif\(([^,]+),([^,]+),([^:\s,]+::[^\(]+\([^\)]*\))\)\s\}\}/umU";

        $content_processed  = preg_replace_callback($pattern_conditions,'self::replace_inline_condition',$content_processed);
        $content_processed  = preg_replace_callback($pattern_conditions_inline_with_method,'self::replace_blitz_inline_conditions',$content_processed);
        $content_processed  = preg_replace_callback($pattern_single_helpers,'self::replace_helper',$content_processed);

        $csrf_replace       = '<input type="hidden" name="_token" value="{{ csrf_token() }}" />';
        $content_processed  = str_replace('<!-- @csrf -->',$csrf_replace,$content_processed);
        $content_processed  = str_replace('@csrf',$csrf_replace,$content_processed);

        return $content_processed;
    }

    protected static function replace_blitz_inline_conditions (array $match):string
    {
        $output = '';
        if ( count($match) === 4 ) {
            $cond_variable   = trim($match[1]);
            $set_variable    = trim($match[2]);
            $end_if          = ltrim($cond_variable,'$');
            $method          = $match[3];
            $output          = '{{ IF '.$cond_variable.' }}{{ '.$set_variable.' }}{{ ELSE }}{{ '.$method.' }}{{ END if-'.$end_if.' }}';
        }
        return $output;
    }

    protected static function replace_inline_condition (array $match):string
    {
        $output = '';
        if ( count($match) === 3 ) {
            $variable       = trim($match[1]);
            $method         = $match[2];
            $end_if         = ltrim($variable,'$');
            $output         = '{{ IF '.$variable.' }}{{ '.$variable.' }}{{ ELSE }}{{ '.$method.' }}{{ END if-'.$end_if.' }}';
        }
        return $output;
    }

    protected static function replace_helper (array $match ):string {
        $output = '';
        if ( count($match) === 2 ) {
            $where_replace  = $match[0];
            $callable       = $match[1];
            [$class,$method]    = explode('::',$callable,2);
            if ( !in_array($class,['php','this']) ) {
                $qualifiedClass = self::getFullQualifiedClass($class);
                if ($qualifiedClass !== '') {
                    $helperCode = $qualifiedClass . '::' . $method;
                    $output     = str_replace($callable, $helperCode, $where_replace);
                    self::addToCallableStack($helperCode);
                }
            } else {
                $output = str_replace($class . '::' . $method, $class . '::' . $method, $where_replace);
            }
        }
        return $output;
    }

    protected static function addToCallableStack (string $code):void
    {
        $code_key = md5($code);
        self::$callableHelpers[self::$current_file][$code_key] = $code;
    }

    protected static function getFullQualifiedClass (string $class):string
    {
        $class_exploded = explode("\\",$class);
        if ( count($class_exploded) > 1 && class_exists($class) ) {
            // ???????????? ?? namespace
            return $class;
        } else {
            // ?????? namespace, ???????????? ?? ???????????????? ?? ??????????????
            if ( isset(self::$namespace_finder) && count(self::$namespace_finder) > 0 ) {
                foreach (self::$namespace_finder as $namespace) {
                    $class_with_namespace = $namespace .'\\'. $class;
                    if (class_exists($class_with_namespace)) {
                        return $class_with_namespace;
                    }
                }
            }
        }
        // ?????? ????????????, ???????? ???????????? ?????????? ????????????
        return '';
    }

    protected static function processMultipleEOL (string $content):string
    {
        return preg_replace("/\n+/",PHP_EOL,$content);
    }

    protected static function getCacheClass ():string
    {
        $cacheType = config('blitz.cache_type','file');
        if ( $cacheType === 'redis' ) {
            return __NAMESPACE__.'\Cache\BlitzTemplateCacheRedis';
        } else {
            return __NAMESPACE__.'\Cache\BlitzTemplateCacheFile';
        }
    }

}
