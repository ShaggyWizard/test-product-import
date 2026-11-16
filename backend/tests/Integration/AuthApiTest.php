<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Firebase\JWT\JWT;

final class AuthApiTest extends ApiTestCase
{
    public function testLoginReturnsTokenThatOpensProtectedRoutes(): void
    {
        $response = $this->request('POST', '/api/auth/login', ['login' => 'admin', 'password' => 'test-password'], auth: false);

        self::assertSame(200, $response->getStatusCode());
        $session = $this->json($response);
        self::assertIsString($session['token']);
        self::assertGreaterThan(time(), $session['expiresAt']);
    }

    public function testWrongPasswordIs401(): void
    {
        $response = $this->request('POST', '/api/auth/login', ['login' => 'admin', 'password' => 'nope'], auth: false);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testEmptyCredentialsAre422(): void
    {
        $response = $this->request('POST', '/api/auth/login', ['login' => ''], auth: false);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['login', 'password'], array_keys($this->json($response)['details']));
    }

    public function testProtectedRoutesRequireToken(): void
    {
        foreach ([['GET', '/api/products'], ['GET', '/api/products/1'], ['POST', '/api/import'], ['GET', '/api/import/latest']] as [$method, $uri]) {
            $response = $this->request($method, $uri, auth: false);
            self::assertSame(401, $response->getStatusCode(), $method . ' ' . $uri);
            self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        }
    }

    public function testPublicRoutesWorkWithoutToken(): void
    {
        self::assertSame(200, $this->request('GET', '/api/health', auth: false)->getStatusCode());
        self::assertSame(200, $this->request('GET', '/api/docs', auth: false)->getStatusCode());
    }

    public function testExpiredAndForgedTokensAreRejected(): void
    {
        $secret = 'test-secret-test-secret-test-secret-123';
        $expired = JWT::encode(['iss' => 'product-import', 'sub' => 'admin', 'exp' => time() - 10], $secret, 'HS256');
        $forged = JWT::encode(['iss' => 'product-import', 'sub' => 'admin', 'exp' => time() + 600], str_repeat('x', 40), 'HS256');

        foreach ([$expired, $forged, 'garbage'] as $token) {
            $request = (new \Slim\Psr7\Factory\ServerRequestFactory())
                ->createServerRequest('GET', '/api/products')
                ->withHeader('Authorization', 'Bearer ' . $token);
            $app = (require \dirname(__DIR__, 2) . '/config/app_factory.php')($this->container);

            self::assertSame(401, $app->handle($request)->getStatusCode());
        }
    }
}
