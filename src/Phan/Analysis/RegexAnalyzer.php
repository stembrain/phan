<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type\ArrayShapeType;
use Phan\Language\UnionType;
use Phan\Library\RegexKeyExtractor;

use ast\Node;
use InvalidArgumentException;

/**
 * This infers the union type of $matches in preg_match,
 * including the number of potential groups if that can be inferred.
 *
 * @see PregRegexPlugin for the plugin that actually emits warnings about invalid regexes
 */
class RegexAnalyzer
{
    public static function getPregMatchUnionType(
        CodeBase $code_base,
        Context $context,
        array $argument_list
    ) : UnionType {
        static $string_array_type = null;
        static $string_type = null;
        static $array_type = null;
        static $shape_array_type = null;
        static $shape_array_inner_type = null;
        if ($string_array_type === null) {
            // Note: Patterns **can** have named subpatterns
            $string_array_type = UnionType::fromFullyQualifiedString('string[]');
            $string_type       = UnionType::fromFullyQualifiedString('string');
            $array_type        = UnionType::fromFullyQualifiedString('array');
            $shape_array_type  = UnionType::fromFullyQualifiedString('array{0:string,1:int}[]');
            $shape_array_inner_type  = UnionType::fromFullyQualifiedString('array{0:string,1:int}');
        }
        $regex_node = $argument_list[0];
        $regex = $regex_node instanceof Node ? (new ContextNode($code_base, $context, $regex_node))->getEquivalentPHPScalarValue() : $regex_node;
        try {
            $regex_group_keys = RegexKeyExtractor::getKeys($regex);
        } catch (InvalidArgumentException $_) {
            $regex_group_keys = null;
        }
        if (\count($argument_list) > 3) {
            $offset_flags_node = $argument_list[3];
            $bit = (new ContextNode($code_base, $context, $offset_flags_node))->getEquivalentPHPScalarValue();
        } else {
            $bit = 0;
        }

        if (!\is_int($bit)) {
            return $array_type;
        }
        // TODO: Support PREG_UNMATCHED_AS_NULL
        if ($bit & PREG_OFFSET_CAPTURE) {
            if (is_array($regex_group_keys)) {
                return self::makeArrayShape($regex_group_keys, $shape_array_inner_type);
            }
            return $shape_array_type;
        }

        if (is_array($regex_group_keys)) {
            return self::makeArrayShape($regex_group_keys, $string_type);
        }
        return $string_array_type;
    }

    /**
     * @param array<int|string,true> $regex_group_keys
     */
    private static function makeArrayShape(
        array $regex_group_keys,
        UnionType $type
    ) : UnionType {
        $field_types = array_map(
            /** @param true $_ */
            function ($_) use ($type) : UnionType {
                return $type;
            },
            $regex_group_keys
        );
        return ArrayShapeType::fromFieldTypes($field_types, false)->asUnionType();
    }
}
