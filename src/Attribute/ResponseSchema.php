<?php

declare(strict_types=1);

namespace Respatch\RespatchBundle\Attribute;

/**
 * Definuje očakávanú JSON štruktúru response pre daný controller action.
 * Schéma je načítaná raz pri cache warmupe a uložená do cache.
 * Response listener ju potom validuje bez použitia reflexie.
 *
 * Formát schémy:
 *   'key'  => 'string'|'int'|'float'|'bool'|'array'|'null'|'scalar'|'any'  — povinný kľúč
 *   'key?' => ...  — voliteľný kľúč
 *
 * @param array<string, mixed> $schema
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class ResponseSchema
{
    /** @param array<string, mixed> $schema */
    public function __construct(
        public readonly array $schema,
    ) {
    }
}
