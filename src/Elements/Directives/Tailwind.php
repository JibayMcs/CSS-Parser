<?php

namespace Jibaymcs\CssParser\Elements\Directives;

use Jibaymcs\CssParser\Serializer;

class Tailwind extends Serializer
{
    public function getRegex(): string
    {
        return '/@tailwind\s*(\w+)\s*;/';
    }

    public function parse(string $cssContent): array
    {
        $matches= $this->getMatches($cssContent);
        $tailwind = null;

        foreach ($matches[1] as $index => $match) {
            $attributes = $this->parseCommonAttributes($cssContent, $matches[0][$index][1]);
            $tailwind[] = array_merge($attributes, [
                'value' => $match[0],
            ]);
        }

        return ['tailwind' => $tailwind];
    }

    public function toCss(array $parsedData): string
    {
        if (isset($parsedData['tailwind']) && is_array($parsedData['tailwind'])) {
            return implode("\n", array_map(function ($directive) {
                $comment = $this->formatComments($directive['comment'] ?? []);
                return "{$comment}@tailwind {$directive['value']};";
            }, $parsedData['tailwind']));
        }
        return '';
    }
}