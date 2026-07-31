<?php

declare(strict_types=1);

namespace AichaDigital\MustacheResolver\Contracts;

/**
 * Declares a class safe to serialise whole into a template.
 *
 * Whole-container serialisation bypasses every per-path check, so it is
 * blocked by default. A class implementing this interface opts itself in
 * without opening the global allow_container_serialization flag — the
 * decision lives next to the code that knows whether it is safe.
 */
interface SafeForTemplateSerialization {}
