<?php

namespace Jibaymcs\CssParser\Elements;

use Jibaymcs\CssParser\Serializer;

class Element extends Serializer
{
    public function getRegex(): ?string
    {
        return null;
    }

    public function parse(string $cssContent): array
    {
        $offset = 0;
        $styleRules = [];

        $length = strlen($cssContent);

        while ($offset < $length) {
            // Ignorer les espaces blancs
            while ($offset < $length && ctype_space($cssContent[$offset])) {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            // Vérifier les commentaires et les ignorer
            if (substr($cssContent, $offset, 2) === '/*') {
                $commentEnd = strpos($cssContent, '*/', $offset + 2);
                if ($commentEnd === false) {
                    // Commentaire non terminé, on arrête le parsing
                    break;
                }
                $offset = $commentEnd + 2;
                continue;
            }

            // Vérifier les directives @ et les ignorer
            if ($cssContent[$offset] === '@') {
                // Trouver la fin de la directive @
                $nextSemicolon = strpos($cssContent, ';', $offset);
                $nextBrace = strpos($cssContent, '{', $offset);
                if ($nextSemicolon !== false && ($nextBrace === false || $nextSemicolon < $nextBrace)) {
                    // Directive @ se terminant par ';'
                    $offset = $nextSemicolon + 1;
                    continue;
                } elseif ($nextBrace !== false) {
                    // Directive @ avec un bloc
                    $startPos = $nextBrace;
                    $endPos = $this->findMatchingBrace($cssContent, $startPos);
                    if ($endPos === false) {
                        // Bloc non terminé, on arrête le parsing
                        break;
                    }
                    $offset = $endPos + 1;
                    continue;
                } else {
                    // Aucune fin de directive trouvée, on arrête le parsing
                    break;
                }
            }

            // Maintenant, nous devrions être sur un sélecteur
            // Trouver la position du prochain '{'
            $startPos = strpos($cssContent, '{', $offset);

            if ($startPos === false) {
                break;
            }

            // Extraire le sélecteur
            $selectorContent = substr($cssContent, $offset, $startPos - $offset);
            $selector = trim($selectorContent);

            // Vérifier si le sélecteur est vide
            if ($selector === '') {
                $offset = $startPos + 1;
                continue;
            }

            // Trouver la position de l'accolade fermante correspondante '}'
            $endPos = $this->findMatchingBrace($cssContent, $startPos);

            if ($endPos === false) {
                // Accolade fermante non trouvée, on arrête le parsing
                break;
            }

            // Extraire le bloc de déclarations
            $declarationsContent = substr($cssContent, $startPos + 1, $endPos - $startPos - 1);

            // Parser les déclarations
            $declarations = $this->parseDeclarations($declarationsContent);

            // Obtenir les attributs communs (commentaires, ligne)
            $attributes = $this->parseCommonAttributes($cssContent, $offset);

            $styleRules[] = array_merge($attributes, [
                'selector' => $selector,
                'declarations' => $declarations,
            ]);

            // Mettre à jour l'offset
            $offset = $endPos + 1;
        }

        if (!empty($styleRules)) {
            return ['styles' => $styleRules];
        }

        return [];
    }

    /**
     * Parse les déclarations de propriétés dans un bloc de déclarations.
     *
     * @param string $content
     * @return array
     */
    private function parseDeclarations(string $content): array
    {
        $declarations = [];
        $lines = explode(';', $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            // Ignorer les commentaires dans les déclarations
            if (strpos($line, '/*') !== false) {
                $line = preg_replace('/\/\*.*?\*\//', '', $line);
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
            }
            $parts = explode(':', $line, 2);
            if (count($parts) == 2) {
                $property = trim($parts[0]);
                $value = trim($parts[1]);
                $declarations[] = [
                    'property' => $property,
                    'value' => $value,
                ];
            }
        }

        return $declarations;
    }

    /**
     * Trouve la position de l'accolade fermante correspondante.
     *
     * @param string $content
     * @param int $startPos
     * @return int|false
     */
    private function findMatchingBrace(string $content, int $startPos)
    {
        $length = strlen($content);
        $braceCount = 0;

        for ($i = $startPos; $i < $length; $i++) {
            if ($content[$i] === '{') {
                $braceCount++;
            } elseif ($content[$i] === '}') {
                $braceCount--;
                if ($braceCount === 0) {
                    return $i;
                }
            } elseif (substr($content, $i, 2) === '/*') {
                // Ignorer les commentaires
                $commentEnd = strpos($content, '*/', $i + 2);
                if ($commentEnd === false) {
                    // Commentaire non terminé, on arrête le parsing
                    break;
                }
                $i = $commentEnd + 1;
            }
        }

        // Pas d'accolade fermante correspondante trouvée
        return false;
    }

    public function toCss(array $parsedData): string
    {
        if (isset($parsedData['styles'])) {
            $css = '';
            foreach ($parsedData['styles'] as $styleRule) {
                $comment = $this->formatComments($styleRule['comment'] ?? []);
                $selector = $styleRule['selector'];
                $declarations = $this->declarationsToCss($styleRule['declarations']);
                $css .= "{$comment}{$selector} {\n{$declarations}}\n\n";
            }
            return $css;
        }

        return '';
    }

    private function declarationsToCss(array $declarations): string
    {
        $css = '';
        foreach ($declarations as $declaration) {
            $property = $declaration['property'];
            $value = $declaration['value'];
            $css .= "\t{$property}: {$value};\n";
        }
        return $css;
    }

    protected function parseCommentsBefore(string $content, int $offset): ?array
    {
        $position = $offset - 1;
        $comments = [];

        // Ignorer les espaces blancs ou les nouvelles lignes avant l'offset
        while ($position >= 0 && ctype_space($content[$position])) {
            $position--;
        }

        // Vérifier s'il y a un commentaire immédiatement avant
        while ($position >= 1 && substr($content, $position - 1, 2) === '*/') {
            // Trouver le début du commentaire
            $commentEnd = $position + 1;
            $commentStart = strrpos($content, '/*', - (strlen($content) - $position));

            if ($commentStart === false) {
                // Pas de début de commentaire trouvé, on sort de la boucle
                break;
            }

            // Vérifier s'il n'y a que des espaces entre le commentaire et la règle CSS
            $betweenCommentAndOffset = substr($content, $commentEnd, $offset - $commentEnd);
            if (trim($betweenCommentAndOffset) !== '') {
                // Il y a du contenu non blanc entre le commentaire et la règle, on arrête
                break;
            }

            // Extraire le contenu du commentaire
            $commentContent = substr($content, $commentStart + 2, $position - $commentStart - 3);
            $comments[] = trim($commentContent);

            // Mettre à jour la position pour chercher d'autres commentaires adjacents
            $position = $commentStart - 1;

            // Ignorer les espaces blancs ou les nouvelles lignes avant le commentaire
            while ($position >= 0 && ctype_space($content[$position])) {
                $position--;
            }
        }

        if (!empty($comments)) {
            // Les commentaires sont collectés en ordre inverse, on les remet dans le bon ordre
            return array_reverse($comments);
        }

        return null;
    }


    protected function formatComments(?array $comments): string
    {
        if ($comments) {
            return implode("\n", array_map(function ($comment) {
                    return "/* {$comment} */";
                }, $comments)) . "\n";
        }
        return '';
    }
}
