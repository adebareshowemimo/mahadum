<?php

namespace App\Services;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Small allow-list sanitizer for administrator-authored transactional email HTML.
 * Scripts, embedded documents, event handlers and unsafe URL/CSS schemes never
 * reach either the preview iframe or an outbound message.
 */
class EmailHtmlSanitizer
{
    /** @var array<string, array<int, string>> */
    private const ATTRIBUTES = [
        '*' => ['class', 'style', 'title', 'align'],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'table' => ['width', 'cellpadding', 'cellspacing', 'border'],
        'td' => ['width', 'height', 'colspan', 'rowspan', 'valign'],
        'th' => ['width', 'height', 'colspan', 'rowspan', 'valign'],
    ];

    /** @var array<int, string> */
    private const TAGS = [
        'a', 'b', 'blockquote', 'br', 'code', 'div', 'em', 'h1', 'h2', 'h3',
        'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 'span',
        'strong', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    /** @var array<int, string> */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div data-email-html-root="1">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('div')->item(0);
        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->cleanChildren($root);

        $clean = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $clean .= $document->saveHTML($child);
        }

        return $clean;
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMComment) {
                $parent->removeChild($node);

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            if (! in_array($tag, self::TAGS, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $this->cleanAttributes($node, $tag);
            $this->cleanChildren($node);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_merge(self::ATTRIBUTES['*'], self::ATTRIBUTES[$tag] ?? []);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            if (! in_array($name, $allowed, true)
                || str_starts_with($name, 'on')
                || ($name === 'style' && $this->unsafeCss($value))
                || (in_array($name, ['href', 'src'], true) && ! $this->safeUrl($value, $name === 'src'))) {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($tag === 'a' && $element->hasAttribute('target')) {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function unsafeCss(string $css): bool
    {
        return (bool) preg_match('/expression\s*\(|javascript\s*:|vbscript\s*:|@import|behavior\s*:|-moz-binding|url\s*\(/i', $css);
    }

    private function safeUrl(string $url, bool $image): bool
    {
        if ($url === '' || str_contains($url, '{{')) {
            return true;
        }

        if (! preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $match)) {
            return str_starts_with($url, '/') || str_starts_with($url, '#');
        }

        $schemes = $image ? ['http', 'https', 'cid'] : ['http', 'https', 'mailto', 'tel'];

        return in_array(strtolower($match[1]), $schemes, true);
    }
}
