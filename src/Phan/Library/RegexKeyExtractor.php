<?php declare(strict_types=1);
namespace Phan\Library;

use InvalidArgumentException;
use function strlen;

/**
 * This contains a heuristic for guessing the offsets and groups that are possible for a given regular expression.
 *
 * This may not be aware of all edge cases.
 */
class RegexKeyExtractor
{
    /**
     * @var string the inner pattern of the regular expression
     */
    private $pattern;

    /**
     * @var int the byte offset in $this->pattern
     */
    private $offset = 0;

    /**
     * @var array<string|int,true> the offsets or names of patterns
     */
    private $matches = [];

    private function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * @param string|mixed $regex
     * @return array<string|int,true>
     * @throws InvalidArgumentException if the regex could not be parsed by these heuristics
     */
    public static function getKeys($regex) : array
    {
        if (!is_string($regex)) {
            throw new InvalidArgumentException("regex is not a string");
        }
        $inner_pattern = self::extractInnerRegexPattern($regex);

        $matcher = new self($inner_pattern . ')');
        $matcher->extractGroup();
        $expected_length = strlen($inner_pattern);
        $parsed_length = $matcher->offset - 1;

        if ($parsed_length !== $expected_length) {
            throw new InvalidArgumentException("Only matched $parsed_length of $expected_length for '$inner_pattern'");
        }
        return $matcher->getMatchKeys();
    }

    /**
     * @throws InvalidArgumentException if an invalid pattern was seen
     */
    private function extractGroup()
    {
        $this->matches[] = true;

        $pattern = $this->pattern;
        $len = strlen($pattern);
        if ($pattern[$this->offset] === '?') {
            throw new InvalidArgumentException('Support for complex patterns is not implemented');
        }
        while ($this->offset < $len) {
            $c = $pattern[$this->offset++];
            if ($c === '\\') {
                // Skip over escaped characters
                $this->offset++;
                continue;
            }
            if ($c === ')') {
                // We have reached the end of this group
                return;
            }
            if ($c === '(') {
                // TODO: Handle ?: and the general case

                $this->extractGroup();
            }
        }
        throw new InvalidArgumentException('Reached the end of the pattern before extracting the group');
    }

    /** @return array<int|string,true> */
    private function getMatchKeys() : array
    {
        return $this->matches;
    }


    /**
     * Extracts everything between the pattern delimiters.
     * @throws InvalidArgumentException if the length mismatched
     */
    private static function extractInnerRegexPattern(string $pattern) : string
    {
        $pattern = \trim($pattern);

        $start_chr = $pattern[0];
        // @phan-suppress-next-line PhanParamSuspiciousOrder this is deliberate
        $i = stripos('({[', $start_chr);
        if ($i !== false) {
            $end_chr = ')}]'[$i];
        } else {
            $end_chr = $start_chr;
        }
        // TODO: Reject characters that preg_match would reject
        $end_pos = \strrpos($pattern, $end_chr);
        if ($end_pos === false) {
            throw new InvalidArgumentException("Failed to find match for '$start_chr'");
        }

        $inner = (string)\substr($pattern, 1, $end_pos - 1);
        if ($i !== false) {
            // Unescape '/x\/y/' as 'x/y'
            $inner = \str_replace('\\' . $start_chr, $start_chr, $inner);
        }
        return $inner;
    }
}
