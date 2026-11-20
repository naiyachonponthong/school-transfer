<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * กรอง HTML จากตัวแก้ไขข่าว: เก็บเฉพาะแท็กและสไตล์ที่ปลอดภัย ตัด script / on* / javascript: ทิ้ง
 * iframe รับเฉพาะวิดีโอ YouTube
 */
class HtmlSanitizer
{
    private const TAGS = [
        'p' => [], 'br' => [], 'div' => [], 'span' => [], 'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [], 'strike' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [], 'sub' => [], 'sup' => [],
        'a' => ['href', 'target'], 'img' => ['src', 'alt', 'width', 'height'], 'iframe' => ['src'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'figure' => [], 'figcaption' => [],
    ];

    /** แท็กที่ทิ้งทั้งก้อน (รวมเนื้อในด้วย) */
    private const DROP = ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'link', 'meta', 'base', 'head', 'title', 'noscript', 'template'];

    private const STYLES = ['color', 'background-color', 'text-align', 'font-size', 'font-weight', 'font-style', 'text-decoration', 'width', 'max-width'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__root');
        if (! $root) {
            return '';
        }
        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }
            if ($tag === 'font') { // execCommand รุ่นเก่า: <font color> > <span style>
                $span = $child->ownerDocument->createElement('span');
                if ($c = self::color($child->getAttribute('color'))) {
                    $span->setAttribute('style', 'color: '.$c);
                }
                while ($child->firstChild) {
                    $span->appendChild($child->firstChild);
                }
                $node->replaceChild($span, $child);
                $child = $span;
                $tag = 'span';
            }
            if (! isset(self::TAGS[$tag])) { // แท็กไม่รู้จัก: เก็บเนื้อใน ทิ้งตัวแท็ก
                self::walk($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }
            self::cleanAttributes($child, $tag);
            if (in_array($tag, ['iframe', 'img'], true) && ! $child->hasAttribute('src')) {
                $node->removeChild($child);

                continue;
            }
            self::walk($child);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $style = self::style($el->getAttribute('style'));
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            if (! in_array($name, self::TAGS[$tag], true)) {
                $el->removeAttribute($attr->name);
            }
        }
        if ($style !== '') {
            $el->setAttribute('style', $style);
        }
        if ($tag === 'a') {
            $href = self::url($el->getAttribute('href'), true);
            $href === null ? $el->removeAttribute('href') : $el->setAttribute('href', $href);
            if ($el->getAttribute('target') === '_blank' || preg_match('~^https?://~i', (string) $href)) {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener');
            } else {
                $el->removeAttribute('target');
            }
        }
        if ($tag === 'img') {
            $src = self::url($el->getAttribute('src'), false);
            $src === null ? $el->removeAttribute('src') : $el->setAttribute('src', $src);
            $el->setAttribute('loading', 'lazy');
            foreach (['width', 'height'] as $a) {
                if ($el->hasAttribute($a) && ! ctype_digit($el->getAttribute($a))) {
                    $el->removeAttribute($a);
                }
            }
        }
        if ($tag === 'iframe') {
            $src = $el->getAttribute('src');
            $id = preg_match('~youtube-nocookie\.com/embed/([A-Za-z0-9_-]{11})~', $src, $m) ? $m[1] : Website::youtubeId($src);
            foreach (iterator_to_array($el->attributes) as $attr) {
                $el->removeAttribute($attr->name);
            }
            if ($id) {
                $el->setAttribute('src', 'https://www.youtube-nocookie.com/embed/'.$id);
                $el->setAttribute('allowfullscreen', 'allowfullscreen');
                $el->setAttribute('loading', 'lazy');
                $el->setAttribute('title', 'YouTube');
            }
        }
    }

    /** ลิงก์ที่อนุญาต: http(s) / ที่อยู่ในเว็บ (/...) / mailto / tel (เฉพาะ a) */
    private static function url(string $url, bool $isLink): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $url = preg_replace('/[\x00-\x20]+/u', '', $url) ?? '';
        if ($url === '') {
            return null;
        }
        if (preg_match('~^https?://~i', $url) || (str_starts_with($url, '/') && ! str_starts_with($url, '//')) || str_starts_with($url, '#')) {
            return $url;
        }
        if ($isLink && preg_match('~^(mailto|tel):~i', $url)) {
            return $url;
        }

        return null;
    }

    private static function style(string $style): string
    {
        $keep = [];
        foreach (explode(';', $style) as $decl) {
            if (! str_contains($decl, ':')) {
                continue;
            }
            [$prop, $val] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (! in_array($prop, self::STYLES, true) || preg_match('~url\s*\(|expression|javascript|[<>"\\\\]~i', $val)) {
                continue;
            }
            if (! preg_match('~^[#a-zA-Z0-9.,%()\s-]{1,60}$~', $val)) {
                continue;
            }
            $keep[] = $prop.': '.$val;
        }

        return implode('; ', $keep);
    }

    private static function color(string $c): ?string
    {
        return preg_match('~^(#[0-9a-fA-F]{3,8}|[a-zA-Z]{3,20})$~', trim($c)) ? trim($c) : null;
    }
}
