<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

/**
 * HTTP level steps: sends requests through the Symfony kernel (no web server
 * required) and asserts on the JSON response.
 */
final class ApiContext implements Context
{
    private ?string $token = null;

    public function __construct(private readonly KernelBrowser $client)
    {
        // Keeps a single container alive across requests so that assertions on
        // the in-memory queue observe the very same transport instance.
        $this->client->disableReboot();
        $this->client->setServerParameter('CONTENT_TYPE', 'application/ld+json');
        $this->client->setServerParameter('HTTP_ACCEPT', 'application/ld+json');
    }

    #[Given('I am not authenticated')]
    public function iAmNotAuthenticated(): void
    {
        $this->token = null;
    }

    #[Given('I am authenticated as :email with password :password')]
    public function iAmAuthenticatedAs(string $email, string $password): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR),
        );

        Assert::same(
            $this->client->getResponse()->getStatusCode(),
            Response::HTTP_OK,
            'Authentication failed: '.$this->client->getResponse()->getContent(),
        );

        $payload = $this->decodeResponse();
        Assert::keyExists($payload, 'token');
        Assert::string($payload['token']);

        $this->token = $payload['token'];
    }

    #[When('I send a :method request to :uri')]
    public function iSendARequestTo(string $method, string $uri): void
    {
        $this->request($method, $uri, null);
    }

    #[When('I send a :method request to :uri with body:')]
    public function iSendARequestToWithBody(string $method, string $uri, PyStringNode $body): void
    {
        $this->request($method, $uri, $body->getRaw());
    }

    #[Then('the response status code should be :code')]
    public function theResponseStatusCodeShouldBe(int $code): void
    {
        Assert::same(
            $this->client->getResponse()->getStatusCode(),
            $code,
            \sprintf("Expected %d, got %%s.\nBody: %s", $code, (string) $this->client->getResponse()->getContent()),
        );
    }

    #[Then('the JSON node :path should be equal to :expected')]
    public function theJsonNodeShouldBeEqualTo(string $path, string $expected): void
    {
        $value = $this->readNode($path);

        Assert::scalar($value, \sprintf('JSON node "%s" is not a scalar.', $path));
        Assert::same((string) $value, $expected);
    }

    #[Then('the JSON node :path should contain :expected')]
    public function theJsonNodeShouldContain(string $path, string $expected): void
    {
        $value = $this->readNode($path);

        Assert::isArray($value);
        $items = [];
        foreach ($value as $item) {
            $items[] = \is_scalar($item) ? (string) $item : '';
        }

        Assert::inArray($expected, $items);
    }

    #[Then('the JSON node :path should have :count element(s)')]
    public function theJsonNodeShouldHaveElements(string $path, int $count): void
    {
        $value = $this->readNode($path);

        Assert::isArray($value);
        Assert::count($value, $count);
    }

    #[Then('the response should report a violation on :property')]
    public function theResponseShouldReportAViolationOn(string $property): void
    {
        $payload = $this->decodeResponse();

        Assert::keyExists($payload, 'violations', 'Response is not a validation error: '.json_encode($payload));
        Assert::isArray($payload['violations']);

        $properties = [];
        foreach ($payload['violations'] as $violation) {
            Assert::isArray($violation);
            $path = $violation['propertyPath'] ?? '';
            $properties[] = \is_string($path) ? $path : '';
        }

        Assert::inArray($property, $properties, \sprintf('Expected a violation on "%s", got: %s', $property, implode(', ', $properties)));
    }

    #[Then('the JSON node :path should be equal to the :other node')]
    public function theJsonNodeShouldBeEqualToTheOtherNode(string $path, string $other): void
    {
        Assert::same($this->readNode($path), $this->readNode($other));
    }

    #[Then('the response header :name should be :value')]
    public function theResponseHeaderShouldBe(string $name, string $value): void
    {
        Assert::same($this->client->getResponse()->headers->get($name), $value);
    }

    public function lastResponseNode(string $path): mixed
    {
        return $this->readNode($path);
    }

    private function request(string $method, string $uri, ?string $body): void
    {
        $server = [
            'CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ];

        if (null !== $this->token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$this->token;
        }

        $this->client->request($method, $uri, server: $server, content: $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        Assert::isArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function readNode(string $path): mixed
    {
        $value = $this->decodeResponse();

        foreach (explode('.', $path) as $segment) {
            Assert::isArray($value, \sprintf('Cannot read "%s": "%s" is not traversable.', $path, $segment));
            Assert::keyExists($value, $segment, \sprintf('Missing JSON node "%s".', $path));

            $value = $value[$segment];
        }

        return $value;
    }
}
