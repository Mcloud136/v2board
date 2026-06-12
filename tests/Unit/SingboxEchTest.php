<?php

namespace Tests\Unit;

use Tests\TestCase;

class SingboxEchTest extends TestCase
{
    private function buildServer(string $type, array $overrides = []): array
    {
        $base = [
            'name' => "test-{$type}",
            'host' => 'example.com',
            'port' => 443,
            'tls' => 1,
            'tls_settings' => [
                'server_name' => 'example.com',
                'allow_insecure' => 0,
            ],
            'network' => 'tcp',
            'network_settings' => [],
        ];
        return array_merge($base, $overrides);
    }

    private function buildHysteriaServer(array $overrides = []): array
    {
        $base = [
            'name' => 'test-hysteria',
            'host' => 'example.com',
            'port' => 443,
            'tls' => 1,
            'tls_settings' => ['server_name' => 'example.com'],
            'server_name' => 'example.com',
            'insecure' => 0,
            'version' => 1,
            'up_mbps' => 100,
            'down_mbps' => 100,
        ];
        return array_merge($base, $overrides);
    }

    public function test_ech_cloudflare_in_vless(): void
    {
        $server = $this->buildServer('vless', [
            'tls_settings' => [
                'server_name' => 'example.com',
                'allow_insecure' => 0,
                'ech' => 'cloudflare',
            ],
        ]);

        $user = (object)['id' => 1, 'uuid' => 'test-uuid'];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildVless');
        $result = $method->invoke($class, 'test-uuid', $server);

        $this->assertArrayHasKey('ech', $result['tls']);
        $this->assertTrue($result['tls']['ech']['enabled']);
        $this->assertEquals('cloudflare-ech.com', $result['tls']['ech']['query_server_name']);
    }

    public function test_ech_custom_in_vless(): void
    {
        $server = $this->buildServer('vless', [
            'tls_settings' => [
                'server_name' => 'example.com',
                'ech' => 'custom',
                'ech_config' => 'AEX+DQBBqwAgACB8VWmnGRfdZIzHgFfqHr3RhPJ4iXo3gN7DZpPqMBN3dgA',
            ],
        ]);

        $user = (object)['id' => 1, 'uuid' => 'test-uuid'];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildVless');
        $result = $method->invoke($class, 'test-uuid', $server);

        $this->assertArrayHasKey('ech', $result['tls']);
        $this->assertTrue($result['tls']['ech']['enabled']);
        $this->assertIsArray($result['tls']['ech']['config']);
    }

    public function test_no_ech_when_not_configured(): void
    {
        $server = $this->buildServer('vless');

        $user = (object)['id' => 1, 'uuid' => 'test-uuid'];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildVless');
        $result = $method->invoke($class, 'test-uuid', $server);

        $this->assertArrayNotHasKey('ech', $result['tls']);
    }

    public function test_ech_cloudflare_in_vmess(): void
    {
        $server = $this->buildServer('vmess', [
            'tls_settings' => [
                'server_name' => 'example.com',
                'ech' => 'cloudflare',
                'fingerprint' => 'chrome',
            ],
        ]);

        $user = (object)['id' => 1, 'uuid' => 'test-uuid'];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildVmess');
        $result = $method->invoke($class, 'test-uuid', $server);

        $this->assertArrayHasKey('ech', $result['tls']);
        $this->assertTrue($result['tls']['ech']['enabled']);
        $this->assertEquals('cloudflare-ech.com', $result['tls']['ech']['query_server_name']);
    }

    public function test_ech_cloudflare_in_trojan(): void
    {
        $server = $this->buildServer('trojan', [
            'server_name' => 'example.com',
            'tls_settings' => [
                'server_name' => 'example.com',
                'ech' => 'cloudflare',
            ],
        ]);

        $user = (object)['id' => 1, 'uuid' => 'test-uuid'];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildTrojan');
        $result = $method->invoke($class, 'test-password', $server);

        $this->assertArrayHasKey('ech', $result['tls']);
        $this->assertTrue($result['tls']['ech']['enabled']);
    }

    public function test_ech_cloudflare_in_hysteria(): void
    {
        $server = $this->buildHysteriaServer([
            'tls_settings' => [
                'server_name' => 'example.com',
                'ech' => 'cloudflare',
            ],
        ]);

        $user = (object)['id' => 1, 'uuid' => 'test-uuid', 'speed_limit' => 0];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildHysteria');
        $result = $method->invoke($class, 'test-password', $server, $user);

        $this->assertArrayHasKey('ech', $result['tls']);
        $this->assertTrue($result['tls']['ech']['enabled']);
    }
}
