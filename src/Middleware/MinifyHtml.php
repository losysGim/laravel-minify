<?php

namespace Fahlisaputra\Minify\Middleware;

use Fahlisaputra\Minify\Helpers\CSS;
use Fahlisaputra\Minify\Helpers\Javascript;

class MinifyHtml extends Minifier
{
    protected const REGEX_REMOVE_COMMENT = "#\s*<!--(?!\[if\s).*?-->\s*|(?<!\>)\n+(?=\<[^!])#s";

    protected function getDomHtml(): string
    {
        return
            (static::$domDocType ?? '') .
            static::$dom->saveHtml(static::$dom->documentElement);
    }

    protected function apply()
    {
        $ignoredCss = $this->getByTagOnlyIgnored('style');
        $ignoredJs = $this->getByTagOnlyIgnored('script');

        if (!static::$minifyCssHasBeenUsed)
            $css = $this->getByTag('style');

        if (!static::$minifyJavascriptHasBeenUsed)
            $js = $this->getByTag('script');

        $this->loadDom(
            $this->replace(
                $this->getDomHtml()
            ),
            true);

        if (isset($css)) {
            $this->append('getByTag', 'style', $this->minifyCssNodes($css));
        }
        if (isset($js)) {
            $this->append('getByTag', 'script', $this->minifyJsNodes($js));
        }

        if (!empty($ignoredCss)) {
            $this->append('getByTagOnlyIgnored', 'style', $ignoredCss);
        }
        if (!empty($ignoredJs)) {
            $this->append('getByTagOnlyIgnored', 'script', $ignoredJs);
        }

        return trim($this->getDomHtml());
    }

    protected function minifyCssNodes(array $cssNodes): array
    {
        $css = new CSS();
        $allowInsertSemicolon = (bool) config('minify.insert_semicolon.css', false);

        return
            array_map(
                fn($node) => $css->replace($node->nodeValue, $allowInsertSemicolon),
                $cssNodes
            );
    }

    protected function isLdJsonScript($el): bool
    {
        return $el->hasAttribute('type') &&
            strtolower($el->getAttribute('type')) === 'application/ld+json';
    }

    protected function minifyJsNodes(array $jsNodes): array
    {
        $javascript = new Javascript();
        $allowInsertSemicolon = (bool) config('minify.insert_semicolon.js', false);
        $obfuscate = (bool) config('minify.obfuscate', false);
        $skipLdJson = (bool) config('minify.skip_ld_json', true);

        return
            array_map(
                function ($node) use ($javascript, $allowInsertSemicolon, $obfuscate, $skipLdJson) {
                    if ($skipLdJson && $this->isLdJsonScript($node))
                        return $node;

                    $value = $javascript->replace($node->nodeValue, $allowInsertSemicolon);
                    if ($obfuscate)
                        $value = $javascript->obfuscate($value);

                    return $value;
                },
                $jsNodes
            );
    }

    protected function append(string $function, string $tags, array $backup)
    {
        $index = 0;
        foreach ($this->{$function}($tags) as $el) {
            $el->nodeValue = '';
            $el->appendChild(static::$dom->createTextNode(is_string($backup[$index]) ? $backup[$index] : $backup[$index]->nodeValue));
            $index++;
        }
    }

    protected function removeComment($value)
    {
        return preg_replace(self::REGEX_REMOVE_COMMENT, '', $value);
    }

    protected function replace($value)
    {
        $value = trim(preg_replace([
            // t = text
            // o = tag open
            // c = tag close
            // Keep important white-space(s) after self-closing HTML tag(s)
            '#<(img|input)(>| .*?>)#s',
            // Remove a line break and two or more white-space(s) between tag(s)
            '#(<!--.*?-->)|(>)(?:\n*|\s{2,})(<)|^\s*|\s*$#s',
            '#(<!--.*?-->)|(?<!\>)\s+(<\/.*?>)|(<[^\/]*?>)\s+(?!\<)#s',
            // t+c || o+t
            '#(<!--.*?-->)|(<[^\/]*?>)\s+(<[^\/]*?>)|(<\/.*?>)\s+(<\/.*?>)#s',
            // o+o || c+c
            '#(<!--.*?-->)|(<\/.*?>)\s+(\s)(?!\<)|(?<!\>)\s+(\s)(<[^\/]*?\/?>)|(<[^\/]*?\/?>)\s+(\s)(?!\<)#s',
            // c+t || t+o || o+t -- separated by long white-space(s)
            '#(<!--.*?-->)|(<[^\/]*?>)\s+(<\/.*?>)#s',
            // empty tag
            '#<(img|input)(>| .*?>)<\/\1>#s',
            // reset previous fix
            '#(&nbsp;)&nbsp;(?![<\s])#',
            // clean up ...
            '#(?<=\>)(&nbsp;)(?=\<)#',
            // --ibid
            '/\s+/',
        ], [
            '<$1$2</$1>',
            '$1$2$3',
            '$1$2$3',
            '$1$2$3$4$5',
            '$1$2$3$4$5$6$7',
            '$1$2$3',
            '<$1$2',
            '$1 ',
            '$1',
            ' ',
        ], $value));

        $allowRemoveComments = (bool) config('minify.remove_comments', true);

        return $allowRemoveComments == false
            ? $value
            : $this->removeComment($value);
    }
}
