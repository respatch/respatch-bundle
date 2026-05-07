<?php

declare(strict_types=1);

namespace Respatch\RespatchBundle\Cache;

use Respatch\RespatchBundle\Attribute\ResponseSchema;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Číta #[ResponseSchema] atribúty z kontrolerov cez reflexiu raz pri cache warmupe.
 * Výsledok uloží do PHP súboru v cache adresári, aby listener nepotreboval reflexiu.
 */
final class ResponseSchemaWarmer implements CacheWarmerInterface
{
    /** @param array<class-string> $controllerClasses */
    public function __construct(
        private readonly array $controllerClasses,
    ) {
    }

    public function isOptional(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $schemas = [];

        foreach ($this->controllerClasses as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(ResponseSchema::class);

                if ($attributes === []) {
                    continue;
                }

                /** @var ResponseSchema $instance */
                $instance = $attributes[0]->newInstance();
                $schemas[$class . '::' . $method->getName()] = $instance->schema;
            }
        }

        $cacheFile = $cacheDir . '/respatch_response_schemas.php';
        file_put_contents(
            $cacheFile,
            '<?php return ' . var_export($schemas, true) . ';' . PHP_EOL,
        );

        return [$cacheFile];
    }
}
