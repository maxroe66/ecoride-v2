<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\JwtService;

final class JwtServiceTest extends TestCase
{
    public function testGenerateAndValidate(): void
    {
        $service = new JwtService('secret-test', 60);
        $token = $service->generate(['user_id' => 123, 'email' => 'u@example.com']);
        $this->assertNotEmpty($token);
        $payload = $service->validate($token);
        $this->assertSame(123, $payload['user_id']);
        $this->assertSame('u@example.com', $payload['email']);
        $this->assertArrayHasKey('exp', $payload);
    }

    public function testExpiredTokenThrows(): void
    {
        $service = new JwtService('secret-test', 1);
        $token = $service->generate(['x' => 1]);
        sleep(2);
        $this->expectException(Exception::class);
        $service->validate($token);
    }
}