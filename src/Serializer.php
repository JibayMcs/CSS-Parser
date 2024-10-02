<?php

namespace Jibaymcs\CssParser;

abstract class Serializer
{
    /**
     * Capture les commentaires et le numéro de ligne pour un élément CSS.
     *
     * @param string $cssContent
     * @param int $offset
     * @return array
     */
    protected function parseCommonAttributes(string $cssContent, int $offset): array
    {
        return [
            'comment' => $this->parseCommentsBefore($cssContent, $offset),
            'line' => $this->getLineNumber($cssContent, $offset),
        ];
    }

    /**
     * Parse les commentaires situés avant un élément CSS.
     *
     * @param string $content
     * @param int $offset
     * @return array|null
     */
    protected function parseCommentsBefore(string $content, int $offset): ?array
    {
        preg_match_all('/\/\*([\s\S]*?)\*\//', substr($content, 0, $offset), $matches, PREG_OFFSET_CAPTURE);

        if (!empty($matches[1])) {
            return array_map(function ($match) {
                return trim($match[0]);
            }, $matches[1]);
        }

        return null;
    }

    /**
     * Retourne le numéro de ligne d'un élément CSS à partir de son offset.
     *
     * @param string $content
     * @param int $offset
     * @return int
     */
    protected function getLineNumber(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }

    /**
     * Convertit un tableau de commentaires en chaîne de caractères CSS.
     *
     * @param array|null $comments
     * @return string
     */
    protected function formatComments(?array $comments): string
    {
        if ($comments) {
            return implode("\n", array_map(function ($comment) {
                    return "/* {$comment} */";
                }, $comments)) . "\n";
        }
        return '';
    }

    protected function getMatches(string $cssContent): array
    {
        preg_match_all($this->getRegex(), $cssContent, $matches, PREG_OFFSET_CAPTURE);

        return $matches;
    }

    /**
     * Méthode à implémenter pour parser le contenu CSS.
     *
     * @param string $cssContent
     * @return array
     */
    abstract public function parse(string $cssContent): array;

    /**
     * Méthode à implémenter pour convertir les données en CSS.
     *
     * @param array $parsedData
     * @return string
     */
    abstract public function toCss(array $parsedData): string;

    /**
     * Méthode à implémenter pour retourner l'expression régulière de l'élément.
     *
     * @return ?string
     */
    abstract public function getRegex(): ?string;
}
