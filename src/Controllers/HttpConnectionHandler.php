<?php

namespace Fahlisaputra\Minify\Controllers;

use Fahlisaputra\Minify\Helpers\CSS;
use Fahlisaputra\Minify\Helpers\Javascript;

class HttpConnectionHandler
{
    public function __invoke($file)
    {
        $content = minifyFileGetContents($file);

        // due to support only for css and js (issue #9)
        if (preg_match("/\.css$/", $file)) {
            $mime = 'text/css';
        } elseif (preg_match("/\.js$/", $file)) {
            $mime = 'application/javascript';
        } else
            $mime = 'text/plain';

        if (config('minify.assets_enabled', true)) {
            if ($mime === 'text/css') {
                $content = (new CSS())->replace($content, (bool) config('minify.insert_semicolon.css', true));
            } elseif ($mime === 'application/javascript') {
                $content = ($js = (new Javascript()))->replace($content, (bool) config('minify.insert_semicolon.js', true));

                if (config('minify.obfuscate', false))
                    $content = $js->obfuscate($content);
            }
        }

        return response($content, 200, [
            'Content-Type'      => $mime . '; charset=UTF-8',
            'Content-Length'    => strlen($content),
            'Etag'              => md5($content),
            'Cache-Control'     => 'public, max-age=31536000'
        ]);
    }
}
