<?php

namespace Jibaymcs\CssParser\Elements\Directives;

use Jibaymcs\CssParser\Serializer;

class Media extends Serializer
{
    public function getRegex(): string
    {
        return '/@media\s+([^{]+)\s*\{/';
    }
    public function parse(string $cssContent): array
    {
        $offset = 0;
        $mediaQueries = [];

        while (preg_match($this->getRegex(), $cssContent, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $mediaQuery = trim($matches[1][0]);
            $startPos = $matches[0][1] + strlen($matches[0][0]) - 1;
            $offset = $startPos;

            // Use a function to find the matching closing brace
            $endPos = $this->findMatchingBrace($cssContent, $startPos);

            if ($endPos === false) {
                // No matching closing brace found, break the loop
                break;
            }

            // Extract the content inside the @media block
            $mediaContent = substr($cssContent, $startPos + 1, $endPos - $startPos - 1);

            // Parse the internal CSS rules
            $rules = $this->parseRules($mediaContent);

            // Get the line number and comments
            $attributes = $this->parseCommonAttributes($cssContent, $matches[0][1]);

            $mediaQueries[] = array_merge($attributes, [
                'media_query' => $mediaQuery,
                'rules' => $rules,
            ]);

            // Update the offset to continue parsing after this @media block
            $offset = $endPos + 1;
        }

        if (!empty($mediaQueries)) {
            return ['media' => $mediaQueries];
        }

        return [];
    }

    /**
     * Parses the CSS rules inside the @media block.
     *
     * @param string $content
     * @return array
     */
    private function parseRules(string $content): array
    {
        $rules = [];
        $offset = 0;

        // Use a pattern to match selectors and their declarations or nested at-rules
        $pattern = '/([^{@]+|@[\w\s\(\)-]+)\s*\{/';

        while (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $selector = trim($matches[1][0]);
            $startPos = $matches[0][1] + strlen($matches[0][0]) - 1;
            $offset = $startPos;

            // Find the matching closing brace
            $endPos = $this->findMatchingBrace($content, $startPos);

            if ($endPos === false) {
                break;
            }

            $blockContent = substr($content, $startPos + 1, $endPos - $startPos - 1);

            if (strpos($selector, '@') === 0) {
                // Handle nested at-rule (e.g., @keyframes, @supports)
                $innerParser = new \Jibaymcs\CssParser\Parser($matches[0][0] . $blockContent . '}');
                $parsedData = $innerParser->parse();
                $rules[] = [
                    'at_rule' => $parsedData,
                ];
            } else {
                // Parse declarations
                $declarations = $this->parseDeclarations($blockContent);

                $rules[] = [
                    'selector' => $selector,
                    'declarations' => $declarations,
                ];
            }

            $offset = $endPos + 1;
        }

        return $rules;
    }

    /**
     * Parses CSS declarations inside a rule.
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
     * Finds the position of the matching closing brace in the CSS content.
     *
     * @param string $content The CSS content.
     * @param int $startPos The position to start searching from (should be at the opening brace).
     * @return int|false The position of the matching closing brace, or false if not found.
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
            }
        }

        // No matching closing brace found
        return false;
    }

    public function toCss(array $parsedData): string
    {
        if (isset($parsedData['media'])) {
            $css = '';
            foreach ($parsedData['media'] as $mediaQuery) {
                $comment = $this->formatComments($mediaQuery['comment'] ?? []);
                $mediaQueryText = $mediaQuery['media_query'];
                $rulesCss = $this->rulesToCss($mediaQuery['rules'] ?? []);
                $css .= "{$comment}@media {$mediaQueryText} {\n{$rulesCss}\n}\n";
            }
            return $css;
        }

        return '';
    }

    private function rulesToCss(array $rules): string
    {
        $css = '';
        foreach ($rules as $rule) {
            if (isset($rule['selector'])) {
                $selector = $rule['selector'];
                $declarations = $this->declarationsToCss($rule['declarations']);
                $css .= "\t{$selector} {\n{$declarations}  }\n";
            } elseif (isset($rule['at_rule'])) {
                // Handle nested at-rule
                $innerCss = $this->toCss($rule['at_rule']);
                $css .= $innerCss . "\n";
            }
        }
        return $css;
    }

    private function declarationsToCss(array $declarations): string
    {
        $css = '';
        foreach ($declarations as $declaration) {
            $property = $declaration['property'];
            $value = $declaration['value'];
            $css .= "\t\t{$property}: {$value};\n";
        }
        return $css;
    }

    protected function parseCommentsBefore(string $content, int $offset): ?array
    {
        $position = $offset - 1;

        // Ignorer les espaces blancs avant l'offset
        while ($position > 0 && ctype_space($content[$position])) {
            $position--;
        }

        // Vérifier s'il y a un commentaire immédiatement avant
        if (substr($content, $position - 1, 2) === '*/') {
            // Trouver le début du commentaire
            $commentEnd = $position + 1;
            $commentStart = strrpos($content, '/*', - (strlen($content) - $commentEnd));

            if ($commentStart !== false) {
                // Vérifier s'il n'y a que des espaces entre le commentaire et la directive
                $betweenCommentAndOffset = substr($content, $commentEnd, $offset - $commentEnd);
                if (trim($betweenCommentAndOffset) === '') {
                    $commentContent = substr($content, $commentStart + 2, $commentEnd - $commentStart - 4);
                    $comments = array_map('trim', explode("\n", $commentContent));
                    return $comments;
                }
            }
        }

        return null;
    }


}
