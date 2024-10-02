<?php

namespace Jibaymcs\CssParser\Concerns;

trait Comments
{
    /**
     * Parse les commentaires dans le CSS.
     *
     * @param string $cssContent
     * @return array
     */
    public function parseComments(string $cssContent): array
    {
        $comments = [];
        preg_match_all('/\/\*([\s\S]*?)\*\//', $cssContent, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $match) {
            $line = $this->getLineNumber($cssContent, $match[1]);
            $comments[] = [
                'line' => $line,
                'value' => trim($match[0]),
            ];
        }

        return $comments;
    }

    /**
     * Convertit un tableau de commentaires en chaîne de caractères CSS.
     *
     * @param array $comments
     * @return string
     */
    public function commentsToCss(array $comments): string
    {
        return implode("\n", array_map(function ($comment) {
            return "/* {$comment['value']} */";
        }, $comments));
    }

    /**
     * Retourne le numéro de ligne à partir d'un offset donné.
     *
     * @param string $content
     * @param int $offset
     * @return int
     */
    public function getLineNumber(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }
}