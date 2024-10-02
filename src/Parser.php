<?php

namespace Jibaymcs\CssParser;

use Jibaymcs\CssParser\Elements\Directives;
use Jibaymcs\CssParser\Elements;

class Parser
{
    private array $parsers = [];

    private string $css;

    public function __construct(string $css)
    {
        $this->css = $css;
        $this->registerParsers();
    }

    public function registerParsers(): void
    {
        $this->parsers[] = new Directives\Charset();
        $this->parsers[] = new Directives\Import();
        $this->parsers[] = new Directives\Tailwind();
        $this->parsers[] = new Directives\Config();
        $this->parsers[] = new Directives\Keyframe();
        $this->parsers[] = new Directives\Media();
        $this->parsers[] = new Elements\Element();
    }

    /**
     * Parse le contenu CSS pour extraire les données.
     *
     * @return array
     */
    public function parse(): array
    {
        $results = [];
        foreach ($this->parsers as $parser) {
            $parsed = $parser->parse($this->getOriginalCss());
            if (!empty($parsed)) {
                $results = array_merge_recursive($results, $parsed);
            }
        }
        return $results;
    }

    /**
     * Convertit les données parsées en chaîne CSS.
     *
     * @param array $parsedData
     * @return string
     */
    public function toCss(array $parsedData): string
    {
        $css = '';
        foreach ($this->parsers as $parser) {
            $css .= $parser->toCss($parsedData) . "\n";
        }
        return $css;
    }

    public function toJson(array $parsedData): string
    {
        return json_encode($parsedData, JSON_PRETTY_PRINT);
    }

    public function getOriginalCss(): string
    {
        return $this->css;
    }
}
