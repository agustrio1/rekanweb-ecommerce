<?php

namespace App\Helpers;

/**
 * Pembersih HTML dari editor rich text.
 * Hanya tag di daftar ALLOWED yang dipertahankan, semua atribut dibuang.
 */
class HtmlSanitizer
{
    private const ALLOWED = [
        'p', 'br', 'strong', 'b', 'em', 'i', 's',
        'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'hr', 'code', 'pre',
    ];

    /** Tag berbahaya: dibuang beserta isinya. */
    private const DROP = [
        'script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template',
        'svg', 'math', 'form', 'textarea', 'select', 'button', 'input',
        'link', 'meta', 'base', 'title', 'head',
    ];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (!class_exists(\DOMDocument::class)) {
            $text = trim(strip_tags($html));

            return $text === '' ? '' : '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return '';
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        $out = trim($out);

        if (trim(strip_tags($out)) === '' && stripos($out, '<hr') === false) {
            return '';
        }

        return $out;
    }

    private static function walk(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->nodeName);

                if (in_array($tag, self::DROP, true)) {
                    $node->removeChild($child);
                    continue;
                }

                self::walk($child);

                if (in_array($tag, self::ALLOWED, true)) {
                    while ($child->attributes->length > 0) {
                        $child->removeAttributeNode($child->attributes->item(0));
                    }
                } else {
                    // Tag tidak dikenal: buang tag-nya, pertahankan isinya.
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
            } elseif (!($child instanceof \DOMText)) {
                $node->removeChild($child); // komentar, instruksi pemrosesan, dll.
            }
        }
    }
}