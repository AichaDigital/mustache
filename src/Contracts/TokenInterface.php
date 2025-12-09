<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

use AichaDigital\MustacheResolver\Core\Token\TokenType;

interface TokenInterface
{
    /**
     * Get the raw mustache content (without braces).
     * Example: "User.name" from "{{User.name}}"
     */
    public function getRaw(): string;

    /**
     * Get the full mustache including braces.
     * Example: "{{User.name}}"
     */
    public function getFull(): string;

    /**
     * Get the classified token type.
     */
    public function getType(): TokenType;

    /**
     * Get the path segments.
     * Example: ["User", "name"] from "User.name"
     */
    public function getPath(): array;

    /**
     * Get the prefix (first segment).
     * Example: "User" from "User.name"
     */
    public function getPrefix(): string;

    /**
     * Get the field path (segments after prefix).
     * Example: ["name"] from "User.name"
     */
    public function getFieldPath(): array;

    /**
     * Check if token has a dynamic field indicator ($).
     */
    public function isDynamic(): bool;

    /**
     * Get function name if token is a function call.
     */
    public function getFunctionName(): ?string;

    /**
     * Get function arguments if token is a function call.
     */
    public function getFunctionArgs(): array;

    /**
     * Get default value if token has null coalesce.
     */
    public function getDefaultValue(): ?string;
}
