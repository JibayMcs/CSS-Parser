<?php

namespace Jibaymcs\CssParser\Elements\Directives;

use Jibaymcs\CssParser\Serializer;

class Config extends Serializer
{
    public function getRegex(): string
    {
        return '/@config\s*[\'"]([^\'"]+)[\'"]\s*;/';
    }

    public function parse(string $cssContent): array
    {
        preg_match($this->getRegex(), $cssContent, $matches, PREG_OFFSET_CAPTURE, 0);

        // Vérifier si les correspondances existent
        if (isset($matches[1]) && isset($matches[0][1])) {
            // Utiliser l'offset de la correspondance pour récupérer les attributs communs
            $attributes = $this->parseCommonAttributes($cssContent, $matches[0][1]);


            // Retourner les résultats sous forme de tableau avec la valeur de la config
            return [
                'config' => array_merge($attributes, [
                    'value' => $matches[1][0], // Récupérer la valeur de la directive @config
                ]),
            ];
        }

        return [];
    }

    public function toCss(array $parsedData): string
    {
        if (isset($parsedData['config']['value'])) {
            $comment = $this->formatComments($parsedData['config']['comment'] ?? []);
            return "{$comment}@config '{$parsedData['config']['value']}';";
        }
        return '';
    }
}