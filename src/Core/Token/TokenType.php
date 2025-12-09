<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Core\Token;

/**
 * Defines the types of mustache tokens that can be parsed and resolved.
 */
enum TokenType: string
{
    /**
     * Model field access: {{User.name}}
     * Detected by: PascalCase prefix
     */
    case MODEL = 'model';

    /**
     * Direct table access: {{users.name}}
     * Detected by: snake_case prefix
     */
    case TABLE = 'table';

    /**
     * Relation chain: {{User.posts.comments}}
     * Detected by: Multiple segments after prefix
     */
    case RELATION = 'relation';

    /**
     * Dynamic field resolution: {{User.$relation.field_indicator}}
     * Detected by: $ prefix on segment
     */
    case DYNAMIC = 'dynamic';

    /**
     * Collection access: {{User.posts.0.title}} or {{User.posts.first.title}}
     * Detected by: Numeric or keyword (first, last) in path
     */
    case COLLECTION = 'collection';

    /**
     * Function call: {{now()}} or {{format(User.date, 'Y-m-d')}}
     * Detected by: Parentheses
     */
    case FUNCTION = 'function';

    /**
     * Variable reference: {{$myVariable}}
     * Detected by: $ prefix at start
     */
    case VARIABLE = 'variable';

    /**
     * Math expression: {{(10 + 5) * 2}}
     * Detected by: Arithmetic operators
     */
    case MATH = 'math';

    /**
     * Null coalesce: {{User.name ?? 'default'}}
     * Detected by: ?? operator
     */
    case NULL_COALESCE = 'null_coalesce';

    /**
     * Literal string (no resolution needed)
     */
    case LITERAL = 'literal';

    /**
     * Unknown/unclassified token
     */
    case UNKNOWN = 'unknown';

    /**
     * Compound expression with USE clause.
     * Example: USE {var} => {{expr}} > 0 && SELECT ...
     * Detected by: Starts with "USE "
     */
    case COMPOUND = 'compound';

    /**
     * USE clause variable declaration.
     * Example: {var} => {{expr}} > 0
     * Internal use during compound parsing
     */
    case USE_DECLARATION = 'use_declaration';

    /**
     * Local variable reference (single braces).
     * Example: {max_power} in statement part
     * Detected by: Single braces {var}
     */
    case LOCAL_VARIABLE = 'local_variable';

    /**
     * Formatter function call.
     * Example: toTimeString({{timestamp}} + 180)
     * Detected by: Known formatter name with parentheses
     */
    case FORMATTER = 'formatter';

    /**
     * Check if this type requires a data accessor.
     */
    public function requiresAccessor(): bool
    {
        return match ($this) {
            self::MODEL,
            self::TABLE,
            self::RELATION,
            self::DYNAMIC,
            self::COLLECTION => true,
            default => false,
        };
    }

    /**
     * Check if this type can have nested paths.
     */
    public function supportsNesting(): bool
    {
        return match ($this) {
            self::MODEL,
            self::TABLE,
            self::RELATION,
            self::DYNAMIC,
            self::COLLECTION => true,
            default => false,
        };
    }

    /**
     * Get human-readable description of this token type.
     */
    public function description(): string
    {
        return match ($this) {
            self::MODEL => 'Model field access',
            self::TABLE => 'Direct table access',
            self::RELATION => 'Relation chain navigation',
            self::DYNAMIC => 'Dynamic field resolution',
            self::COLLECTION => 'Collection/array access',
            self::FUNCTION => 'Function call',
            self::VARIABLE => 'Variable reference',
            self::MATH => 'Math expression',
            self::NULL_COALESCE => 'Null coalesce expression',
            self::LITERAL => 'Literal value',
            self::UNKNOWN => 'Unknown token type',
            self::COMPOUND => 'Compound expression with USE clause',
            self::USE_DECLARATION => 'USE clause variable declaration',
            self::LOCAL_VARIABLE => 'Local variable reference',
            self::FORMATTER => 'Formatter function call',
        };
    }

    /**
     * Check if this type is part of compound expression system.
     */
    public function isCompoundRelated(): bool
    {
        return match ($this) {
            self::COMPOUND,
            self::USE_DECLARATION,
            self::LOCAL_VARIABLE,
            self::FORMATTER => true,
            default => false,
        };
    }
}
