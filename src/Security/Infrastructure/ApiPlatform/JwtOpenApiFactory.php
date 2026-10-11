<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\ApiPlatform;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * `json_login` is handled by the security firewall, so API Platform knows
 * nothing about it. This decorator documents the token endpoint and declares
 * the bearer security scheme, keeping the OpenAPI contract complete.
 */
#[AsDecorator('api_platform.openapi.factory')]
final readonly class JwtOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private OpenApiFactoryInterface $decorated,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $components = $openApi->getComponents();

        $securitySchemes = $components->getSecuritySchemes() ?? new \ArrayObject();
        $securitySchemes['bearerAuth'] = new \ArrayObject([
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ]);

        $schemas = $components->getSchemas() ?? new \ArrayObject();
        $schemas['Credentials'] = new \ArrayObject([
            'type' => 'object',
            'required' => ['email', 'password'],
            'properties' => [
                'email' => ['type' => 'string', 'format' => 'email', 'example' => 'admin@example.com'],
                'password' => ['type' => 'string', 'format' => 'password', 'example' => 'admin'],
            ],
        ]);
        $schemas['Token'] = new \ArrayObject([
            'type' => 'object',
            'properties' => ['token' => ['type' => 'string']],
        ]);

        $openApi = $openApi
            ->withComponents($components->withSecuritySchemes($securitySchemes)->withSchemas($schemas))
            ->withSecurity([['bearerAuth' => []]]);

        $openApi->getPaths()->addPath('/api/login', new PathItem(post: new Operation(
            operationId: 'login',
            tags: ['Authentication'],
            responses: [
                '200' => new Response(
                    description: 'JWT issued.',
                    content: new \ArrayObject([
                        'application/json' => ['schema' => ['$ref' => '#/components/schemas/Token']],
                    ]),
                ),
                '401' => new Response(description: 'Invalid credentials.'),
                '429' => new Response(description: 'Too many login attempts.'),
            ],
            summary: 'Exchanges credentials for a JWT.',
            requestBody: new RequestBody(
                description: 'Credentials of an existing API user.',
                content: new \ArrayObject([
                    'application/json' => ['schema' => ['$ref' => '#/components/schemas/Credentials']],
                ]),
                required: true,
            ),
            security: [],
        )));

        return $openApi;
    }
}
