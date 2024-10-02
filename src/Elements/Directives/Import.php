<?php

namespace Jibaymcs\CssParser\Elements\Directives;

use Jibaymcs\CssParser\Serializer;

class Import extends Serializer
{

    public function getRegex(): string
    {
        return '/@import\s*[\'"]([^\'"]+)[\'"]\s*;/';
    }

    public function parse(string $cssContent): array
    {
        $matches= $this->getMatches($cssContent);
        $imports = null;

        foreach ($matches[1] as $index => $match) {
            $attributes = $this->parseCommonAttributes($cssContent, $this->getMatches($cssContent)[0][$index][1]);
            $imports[] = array_merge($attributes, [
                'value' => $match[0],
            ]);
        }

        return ['imports' => $imports];
    }

    public function toCss(array $parsedData): string
    {
        if (isset($parsedData['imports']) && is_array($parsedData['imports'])) {
            return implode("\n", array_map(function ($import) {
                $comment = $this->formatComments($import['comment'] ?? []);
                return "{$comment}@import '{$import['value']}';";
            }, $parsedData['imports']));
        }
        return '';
    }

}