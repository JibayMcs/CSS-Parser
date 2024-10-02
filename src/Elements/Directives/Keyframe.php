<?php

namespace Jibaymcs\CssParser\Elements\Directives;

use Jibaymcs\CssParser\Serializer;

class Keyframe extends Serializer
{
    public function getRegex(): string
    {
        // Regex pour capturer les directives @keyframes et les étapes associées
        return '/@keyframes\s+([a-zA-Z0-9_-]+)\s*\{(.*?)\}/s';
    }

    public function parse(string $cssContent): array
    {
        $offset = 0;
        $keyframes = [];

        // Simple regex to find the @keyframes directive and the name
        $pattern = '/@keyframes\s+([a-zA-Z0-9_-]+)\s*\{/';

        while (preg_match($pattern, $cssContent, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $name = $matches[1][0];
            $startPos = $matches[0][1] + strlen($matches[0][0]);
            $offset = $startPos;

            // Use a function to find the matching closing brace
            $endPos = $this->findMatchingBrace($cssContent, $startPos - 1);

            if ($endPos === false) {
                // No matching closing brace found, break the loop
                break;
            }

            // Extract the content inside the @keyframes block
            $keyframeContent = substr($cssContent, $startPos, $endPos - $startPos);

            // Parse the internal steps
            $steps = $this->parseSteps($keyframeContent);

            // Get the line number and comments
            $attributes = $this->parseCommonAttributes($cssContent, $matches[0][1]);

            $keyframes[] = array_merge($attributes, [
                'name' => $name,
                'steps' => $steps,
            ]);

            // Update the offset to continue parsing after this keyframe
            $offset = $endPos + 1;
        }

        if (!empty($keyframes)) {
            return ['keyframes' => $keyframes];
        }

        return [];
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

    /**
     * Parse les étapes d'une animation @keyframes.
     *
     * @param string $content
     * @return array
     */
    private function parseSteps(string $content): array
    {
        // Use 's' modifier to handle multi-line content
        $stepPattern = '/([a-zA-Z0-9%,\s]+)\s*\{(.*?)\}/s';
        preg_match_all($stepPattern, $content, $stepMatches, PREG_SET_ORDER);

        $steps = [];
        foreach ($stepMatches as $stepMatch) {
            $stepIdentifier = trim($stepMatch[1]);
            $rulesContent = trim($stepMatch[2]);

            $steps[] = [
                'step' => $stepIdentifier,
                'rules' => $this->parseRules($rulesContent),
            ];
        }

        return $steps;
    }

    /**
     * Parse les règles CSS dans une étape de @keyframes.
     *
     * @param string $rulesContent
     * @return array
     */
    private function parseRules(string $rulesContent): array
    {
        $rules = [];
        // Use 's' modifier to handle multi-line values
        $rulePattern = '/([a-zA-Z-]+)\s*:\s*(.*?);/s';
        preg_match_all($rulePattern, $rulesContent, $ruleMatches, PREG_SET_ORDER);

        foreach ($ruleMatches as $ruleMatch) {
            $rules[] = [
                'property' => trim($ruleMatch[1]),
                'value' => trim($ruleMatch[2]),
            ];
        }

        return $rules;
    }

    protected function parseCommentsBefore(string $content, int $offset): ?array
    {
        // Get content from the last non-space character before offset
        $position = $offset - 1;
        while ($position > 0 && ctype_space($content[$position])) {
            $position--;
        }

        // Check if there's a comment immediately before
        if (substr($content, $position - 1, 2) === '*/') {
            // Find the start of the comment
            $commentEnd = $position + 1;
            $commentStart = strrpos(substr($content, 0, $commentEnd), '/*');

            if ($commentStart !== false) {
                $commentContent = substr($content, $commentStart + 2, $commentEnd - $commentStart - 4);
                $comments = array_map('trim', explode("\n", $commentContent));
                return $comments;
            }
        }

        return null;
    }

    public function toCss(array $parsedData): string
    {
        if (isset($parsedData['keyframes'])) {
            $css = '';
            foreach ($parsedData['keyframes'] as $keyframe) {
                $comment = $this->formatComments($keyframe['comment'] ?? []);
                $name = $keyframe['name'];
                $steps = $this->stepsToCss($keyframe['steps'] ?? []);

                $css .= "{$comment}@keyframes {$name} {\n{$steps}\n}\n";
            }
            return $css;
        }

        return '';
    }

    /**
     * Convertit les étapes en CSS.
     *
     * @param array $steps
     * @return string
     */
    private function stepsToCss(array $steps): string
    {
        $css = '';
        foreach ($steps as $step) {
            $rules = $this->rulesToCss($step['rules']);
            $css .= "\t{$step['step']} {\n{$rules}  }\n";
        }
        return $css;
    }

    /**
     * Convertit les règles d'une étape en CSS.
     *
     * @param array $rules
     * @return string
     */
    private function rulesToCss(array $rules): string
    {
        $css = '';
        foreach ($rules as $rule) {
            $css .= "\t\t{$rule['property']}: {$rule['value']};\n";
        }
        return $css;
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
