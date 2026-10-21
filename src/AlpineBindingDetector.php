<?php

declare(strict_types=1);

namespace JoelStein\LaravelT;

class AlpineBindingDetector
{
    /**
     * Matches an opening tag, allowing quoted attribute values to contain
     * `>` and attributes to span multiple lines.
     */
    private const TAG_PATTERN = '/<([a-zA-Z][\w:.-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/';

    /**
     * Matches a `:name="..."` or `x-bind:name="..."` attribute. The leading
     * alternatives consume other quoted values whole, so a colon inside one
     * (e.g. `wire:click="a:b"`) is never mistaken for a binding.
     */
    private const ATTRIBUTE_PATTERN = '/"[^"]*"|\'[^\']*\'|(?<=\s)((?:x-bind)?:[\w.-][\w.:-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/';

    /**
     * Matches a t() or @t() call, but not identifiers that end in t.
     */
    private const CALL_PATTERN = '/(?<![\w$.>])@?t\s*\(/';

    /**
     * Find colon-bound attributes on plain HTML elements whose value calls
     * t(). Blade leaves those attributes alone, so Alpine evaluates the
     * value as JavaScript in the browser, where t() is not defined.
     *
     * @return list<array{line: int, tag: string, attribute: string}>
     */
    public function detect(string $contents): array
    {
        if (! preg_match_all(self::TAG_PATTERN, $contents, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $found = [];

        foreach ($tags as $tag) {
            $name = $tag[1][0];
            if ($this->isComponent($name)) {
                continue;
            }

            if (! preg_match_all(self::ATTRIBUTE_PATTERN, $tag[2][0], $attributes, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL)) {
                continue;
            }

            foreach ($attributes as $attribute) {
                if ($attribute[1][0] === null) {
                    continue;
                }

                $value = $attribute[2][0] ?? $attribute[3][0] ?? '';
                if (! preg_match(self::CALL_PATTERN, $value)) {
                    continue;
                }

                $offset = $tag[2][1] + $attribute[1][1];

                $found[] = [
                    'line' => substr_count(substr($contents, 0, $offset), "\n") + 1,
                    'tag' => $name,
                    'attribute' => $attribute[1][0],
                ];
            }
        }

        return $found;
    }

    /**
     * Blade components use the `x-` prefix (`<x-alert>`) or a namespace
     * (`<x:alert>`, `<flux:input>`, `<livewire:counter>`).
     */
    protected function isComponent(string $name): bool
    {
        return str_starts_with($name, 'x-') || str_contains($name, ':');
    }
}
