<?php

declare(strict_types=1);

namespace Respatch\RespatchBundle\EventListener;

use Opis\JsonSchema\Validator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Validuje JSON response podľa JSON Schema (opis/json-schema) načítanej z cache (nie reflexiou).
 * Schéma je indexovaná kľúčom "Trieda::metóda" a uložená pri cache warmupe.
 */
final class ResponseSchemaListener
{
    /** @var array<string, mixed>|null */
    private ?array $schemas = null;

    private ?Validator $validator = null;

    public function __construct(
        private readonly string $cacheDir,
        private readonly string $environment,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        if (!$response instanceof JsonResponse) {
            return;
        }
		return;

        [$controller, $action] = $event->getRequest()->attributes->get('_controller');

        if (!\is_string($controller) || !\is_string($action)) {
            return;
        }

        $schema = $this->loadSchemas()[$controller."::".$action] ?? null;


        if ($schema === null) {
            return;
        }

        try {
            $this->validate($response, $schema, $controller."::".$action);
        } catch (\RuntimeException $e) {
            $this->logger->critical('API contract violation', [
                'controller' => $controller."::".$action,
                'message' => $e->getMessage(),
            ]);

            if ($this->environment !== 'prod') {
                throw $e;
            }
        }
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function validate(JsonResponse $response, array $schema, string $controller): void
    {
        $content = $response->getContent();

        if ($content === false || $content === '') {
            throw new \RuntimeException('Response has no content.');
        }

        $data = json_decode($content);

        if (!\is_object($data) && !\is_array($data)) {
            throw new \RuntimeException('Response is not a valid JSON object.');
        }

        $schemaObject = json_decode((string) json_encode($schema));
        $result = $this->getValidator()->validate($data, $schemaObject);

        if (!$result->isValid()) {
            $error = $result->error();
            $messages = [];

            if ($error !== null) {
                foreach ($error->nested() as $nested) {
                    $messages[] = \sprintf('[%s] %s', $nested->data()->path(), $nested->message());
                }

                if ($messages === []) {
                    $messages[] = $error->message();
                }
            }

            throw new \RuntimeException(
                \sprintf('API contract violation for "%s": %s', $controller, implode('; ', $messages)),
            );
        }
    }

    private function getValidator(): Validator
    {
        return $this->validator ??= new Validator();
    }

    /** @return array<string, mixed> */
    private function loadSchemas(): array
    {
        if ($this->schemas !== null) {
            return $this->schemas;
        }

        $cacheFile = $this->cacheDir . '/respatch_response_schemas.php';

        if (!\file_exists($cacheFile)) {
            return $this->schemas = [];
        }

        /** @var array<string, mixed> $loaded */
        $loaded = require $cacheFile;

        return $this->schemas = $loaded;
    }
}
