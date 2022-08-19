<?php

namespace NickyX3\Blitz\Exceptions;

use Illuminate\Http\Response;

class BlitzException extends \Exception
{
    protected Response $response;

    public function __construct($message, $errorLevel = 0) {
        parent::__construct ( $message, $errorLevel );

        $this->response     = response('',500)->withHeaders(['Content-type'=>'text/html']);
        $message            = $this->getMessage();
        $file               = $this->getFile();
        $line               = $this->getLine();
        // try explode message to message and template content
        $separator          = PHP_EOL.'<!-- SEPARATOR -->'.PHP_EOL;
        $exploded           = explode($separator,$message,4);
        $original_message   = $exploded[0] ?? '';
        $template_name      = $exploded[1] ?? null;
        $template_data      = $exploded[2] ?? '';
        $template           = $exploded[3] ?? null;
        if ( env('APP_DEBUG') === true ) {
            $template_html   = self::getPageTemplate();
            $message_code    = $file.' on line: '.$line;
            $template_html   = str_replace('{{ $message_code }}',$message_code,$template_html);
            $template_html   = str_replace('{{ $original_message }}',$original_message,$template_html);
            if ( !is_null($template_name)) {
                $template_html = str_replace('{{ $template_name }}', $template_name, $template_html);
            }
            if ( $template_data !== '' && $decoded_data = json_decode($template_data,1) ) {
                $data_scope = self::getDataScope($decoded_data);
                $template_html = str_replace('{{ $data_scope }}', $data_scope, $template_html);
            }
            if ( !is_null($template) ) {
                // have template
                $numerating      = $this->numerateTemplate($template,$message);
                $template_html   = str_replace('{{ $nums }}',implode(PHP_EOL,$numerating['nums']),$template_html);
                $template_html   = str_replace('{{ $code }}',implode(PHP_EOL,$numerating['code']),$template_html);
                $this->response  = response($template_html,500)->withHeaders(['Content-type'=>'text/html']);
            }
        } else {
            abort(500);
        }
    }

    protected function getLineAndPos (string $message):array
    {
        $matches    = [];
        $pattern    = '/line\s(\d+),\spos\s(\d+)\)/iu';
        preg_match_all($pattern, $message, $matches, PREG_SET_ORDER, 0);
        if ( isset($matches[0]) && count($matches[0]) === 3 ) {
            return ['line'=>$matches[0][1],'pos'=>$matches[0][2]];
        }
        return ['line'=>0,'pos'=>0];
    }
    protected function numerateTemplate ( string $template, string $message):array
    {
        $positions = $this->getLineAndPos($message);
        $lines          = explode(PHP_EOL,$template);
        array_unshift($lines,'');
        $lines_count    = count($lines);

        $nums = [];
        for($i=1;$i<$lines_count;$i++){
            $nums[] = $i;
        }
        $code = [];
        for($i=1;$i<$lines_count;$i++){
            $line = $lines[$i];
            if ( $i == $positions['line'] ) {
                $code[] = self::highLightLine($line,$positions['pos']);
            } else {
                $code[] = htmlspecialchars($line);
            }
        }
        return ['nums'=>$nums,'code'=>$code];
    }
    protected static function highLightLine ( string $line, int $pos=0):string {
        $f = htmlspecialchars(mb_substr($line,0,$pos));
        $char = '<span class="high-light">'.htmlspecialchars(mb_substr($line,$pos)).'</span>';
        return '<span class="line-high-light">'.$f.$char.'</span>';
    }

    protected static function getDataScope (mixed $data):string
    {
        return '<div class="scope-message"><div>Data Scope</div><div class="carret"></div></div>
                <div class="data-scope" style="display: none"><pre><code>'.print_r($data,1).'</code></pre></div>
                <script>
                    $(".scope-message").on("click",function (){
                        if ( $(this).hasClass("opened") ) {
                            $(this).removeClass("opened");
                        } else {
                            $(this).addClass("opened");
                        }
                        $(".data-scope").toggle();
                    });
                </script>
                ';
    }

    protected static function getPageTemplate ():string
    {
        return '<!doctype html>
                <html lang="ru">
                    <head>
                        <title>Blitz Exception</title>
                        <link href="/css/nickyx3/blitz/exception.css" rel="stylesheet">
                        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                    </head>
                    <body class="exception">
                        <div class="exception-message">Error in: {{ $message_code }}</div>
                        <div class="exception-message">Message: {{ $original_message }}</div>
                        <div class="exception-message">Template file: {{ $template_name }}</div>
                        {{ $data_scope }}
                        <div class="num-container">
                            <div class="nums"><pre><code>{{ $nums }}</code></pre></div>
                            <div class="lines"><pre><code>{{ $code }}</code></pre></div>
                        </div>
                    </body>
                </html>';
    }
}
