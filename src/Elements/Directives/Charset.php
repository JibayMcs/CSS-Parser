<?php

namespace Jibaymcs\CssParser\Elements\Directives;

use Jibaymcs\CssParser\Serializer;

class Charset extends Serializer
{
    public function getRegex(): string
    {
        // Regex pour capturer la directive @charset
        return '/@charset\s+["\']([^"\']+)["\']\s*;/';
    }

    public function parse(string $cssContent): array
    {
        preg_match_all($this->getRegex(), $cssContent, $matches, PREG_OFFSET_CAPTURE);

        $charsets = null;

        if (isset($matches[1]) && is_array($matches[1])) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                // Récupérer la valeur du charset
                $charsetValue = $matches[1][$i][0];

                // Obtenir l'offset pour récupérer la ligne et les commentaires
                $offset = $matches[0][$i][1];

                $attributes = $this->parseCommonAttributes($cssContent, $offset);

                $charsets[] = array_merge($attributes, [
                    'value' => $charsetValue,
                ]);
            }
        }

        if (!empty($charsets)) {
            return ['charset' => $charsets];
        }

        return ['charset' => null];
    }

    public function toCss(array $parsedData): string
    {
        if (isset($parsedData['charset'])) {
            $css = '';
            foreach ($parsedData['charset'] as $charset) {
                $comment = $this->formatComments($charset['comment'] ?? []);
                $value = $charset['value'];

                $css .= "{$comment}@charset \"{$value}\";\n";
            }
            return $css;
        }

        return '';
    }

}
