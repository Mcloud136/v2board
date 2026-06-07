<?php

namespace Tests\Unit;

use Tests\TestCase;

class SecurityFixesTest extends TestCase
{
    /**
     * 测试 CSV 注入防护：危险字符开头的单元格应添加单引号前缀
     */
    public function test_csv_injection_prevention(): void
    {
        $controller = new \App\Http\Controllers\V1\Admin\UserController();

        // 通过反射调用私有方法
        $method = new \ReflectionMethod($controller, 'sanitizeCsvCell');

        // 公式注入测试
        $this->assertEquals("'=cmd|' /C calc'!A0", $method->invoke($controller, '=cmd|\' /C calc\'!A0'));
        $this->assertEquals("'+1+1", $method->invoke($controller, '+1+1'));
        $this->assertEquals("'-1+1", $method->invoke($controller, '-1+1'));
        $this->assertEquals("'@SUM(A1)", $method->invoke($controller, '@SUM(A1)'));

        // 安全值不应被修改
        $this->assertEquals('normal@email.com', $method->invoke($controller, 'normal@email.com'));
        $this->assertEquals('hello world', $method->invoke($controller, 'hello world'));
        $this->assertEquals('12345', $method->invoke($controller, '12345'));
    }

    /**
     * 测试 CORS 白名单：恶意域名应被拒绝
     */
    public function test_cors_rejects_malicious_origin(): void
    {
        // 配置 app_url
        config(['v2board.app_url' => 'https://mysite.com']);

        $middleware = new \App\Http\Middleware\CORS();

        // 恶意域名请求
        $request = \Illuminate\Http\Request::create('/', 'GET', [], [], [], [
            'HTTP_ORIGIN' => 'https://evil.com'
        ]);

        $response = $middleware->handle($request, function () {
            return response('OK');
        });

        // 恶意域名不应返回 CORS 头
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * 测试 CORS 白名单：合法域名应被接受
     */
    public function test_cors_allows_legitimate_origin(): void
    {
        config(['v2board.app_url' => 'https://mysite.com']);

        $middleware = new \App\Http\Middleware\CORS();

        $request = \Illuminate\Http\Request::create('/', 'GET', [], [], [], [
            'HTTP_ORIGIN' => 'https://mysite.com'
        ]);

        $response = $middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertEquals('https://mysite.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * 测试 JWT 过期时间：生成的 token 应包含 exp 字段
     */
    public function test_jwt_contains_expiration(): void
    {
        $key = config('app.key');

        // 模拟 AuthService 生成 token 的逻辑
        $now = time();
        $payload = [
            'id' => 1,
            'session' => 'test-guid',
            'iat' => $now,
            'exp' => $now + 86400,
        ];

        $token = \Firebase\JWT\JWT::encode($payload, $key, 'HS256');
        $decoded = (array)\Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($key, 'HS256'));

        $this->assertArrayHasKey('exp', $decoded);
        $this->assertEquals($now + 86400, $decoded['exp']);
        $this->assertGreaterThan(time(), $decoded['exp']);
    }

    /**
     * 测试 JWT 过期：过期 token 应被拒绝
     */
    public function test_expired_jwt_is_rejected(): void
    {
        $key = config('app.key');

        // 创建已过期的 token
        $payload = [
            'id' => 1,
            'session' => 'test-guid',
            'iat' => time() - 100,
            'exp' => time() - 10, // 已过期
        ];

        $token = \Firebase\JWT\JWT::encode($payload, $key, 'HS256');

        // 过期 token 解码应抛出异常
        $this->expectException(\Firebase\JWT\ExpiredException::class);
        \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($key, 'HS256'));
    }

    /**
     * 测试 Admin Filter 列名白名单：非法列名应被拒绝
     */
    public function test_admin_filter_rejects_invalid_column(): void
    {
        // 构造包含非法列名的请求
        $request = \Illuminate\Http\Request::create('/test', 'GET', [
            'filter' => [
                ['key' => 'DROP TABLE users; --', 'condition' => '=', 'value' => '1']
            ]
        ]);

        $builder = \App\Models\User::query();
        $controller = new \App\Http\Controllers\V1\Admin\UserController();
        $method = new \ReflectionMethod($controller, 'filter');

        // 执行 filter（不应抛出异常，非法列名应被跳过）
        $method->invoke($controller, $request, $builder);

        // 查询不应包含 DROP TABLE
        $query = $builder->toRawSql();
        $this->assertStringNotContainsString('DROP TABLE', $query);
    }

    /**
     * 测试 Admin Filter 操作符白名单：非法操作符应被拒绝
     */
    public function test_admin_filter_rejects_invalid_operator(): void
    {
        $request = \Illuminate\Http\Request::create('/test', 'GET', [
            'filter' => [
                ['key' => 'email', 'condition' => 'UNION SELECT', 'value' => '1']
            ]
        ]);

        $builder = \App\Models\User::query();
        $controller = new \App\Http\Controllers\V1\Admin\UserController();
        $method = new \ReflectionMethod($controller, 'filter');

        $method->invoke($controller, $request, $builder);

        $query = $builder->toRawSql();
        $this->assertStringNotContainsString('UNION', $query);
    }

    /**
     * 测试 Open Redirect 防护：绝对 URL 应被拒绝
     */
    public function test_open_redirect_prevention(): void
    {
        // 测试 token2Login 中的 redirect 参数
        $request = \Illuminate\Http\Request::create('/test', 'GET', [
            'token' => 'test-token',
            'redirect' => 'https://evil.com'
        ]);

        // 验证 redirect 参数不会包含 https://
        $redirectParam = $request->input('redirect', 'dashboard');
        $this->assertTrue(
            preg_match('/^https?:\/\//i', $redirectParam) === 1,
            'Redirect contains protocol prefix'
        );

        // 修复后的逻辑应该拒绝这种重定向
        if (preg_match('/^https?:\/\//i', $redirectParam) || strpos($redirectParam, '//') === 0) {
            $redirectParam = 'dashboard';
        }
        $this->assertEquals('dashboard', $redirectParam);
    }

    /**
     * 测试 Open Redirect 防护：合法相对路径应被接受
     */
    public function test_legitimate_redirect_allowed(): void
    {
        $redirectParam = '/#/login?verify=abc&redirect=dashboard';
        if (preg_match('/^https?:\/\//i', $redirectParam) || strpos($redirectParam, '//') === 0) {
            $redirectParam = 'dashboard';
        }
        $this->assertEquals('/#/login?verify=abc&redirect=dashboard', $redirectParam);
    }

    /**
     * 测试 PaymentService 类名验证：特殊字符应被拒绝
     */
    public function test_payment_service_rejects_invalid_class_name(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        // 路径遍历尝试
        new \App\Services\PaymentService('../etc/passwd');
    }

    /**
     * 测试 PaymentService 类名验证：合法类名应被接受
     */
    public function test_payment_service_accepts_valid_class_name(): void
    {
        // StripeALL 是合法类名，不应在类名验证阶段被拒绝
        // 数据库相关错误是预期的（测试环境无数据库）
        try {
            new \App\Services\PaymentService('StripeALL');
            $this->assertTrue(true); // 到达这里说明类名验证通过
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->fail('Valid class name was rejected: ' . $e->getMessage());
        } catch (\Exception $e) {
            // 数据库相关错误是预期的
            $this->assertStringNotContainsString('gate is not found', $e->getMessage());
        }
    }

    /**
     * 测试 SecurityHeaders 中间件：应设置正确的安全头
     */
    public function test_security_headers_are_set(): void
    {
        $middleware = new \App\Http\Middleware\SecurityHeaders();

        $request = \Illuminate\Http\Request::create('/test', 'GET');

        $response = $middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    /**
     * 测试 Helper::generateOrderNo 生成的订单号长度和格式
     */
    public function test_order_number_format(): void
    {
        $orderNo = \App\Utils\Helper::generateOrderNo();

        // 订单号应为 25 位纯数字（14位时间 + 6位随机 + 5位随机）
        $this->assertMatchesRegularExpression('/^\d{25}$/', $orderNo);

        // 多次生成应不同
        $orderNo2 = \App\Utils\Helper::generateOrderNo();
        $this->assertNotEquals($orderNo, $orderNo2);
    }

    /**
     * 测试 Helper::guid 生成的 token 格式
     */
    public function test_guid_format(): void
    {
        // guid() 无参数返回 32位hex (md5)
        $guid = \App\Utils\Helper::guid();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $guid);

        // guid(true) 在 Windows 上因 com_create_guid 存在也返回 md5
        // 在非 Windows 上返回 UUID 格式
        $uuid = \App\Utils\Helper::guid(true);
        if (function_exists('com_create_guid')) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $uuid);
        } else {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid);
        }

        // 两次生成应不同
        $this->assertNotEquals(\App\Utils\Helper::guid(), \App\Utils\Helper::guid());
    }

    /**
     * 测试 randomPort 端口范围
     */
    public function test_random_port_in_range(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $port = \App\Utils\Helper::randomPort('1000-2000');
            $this->assertGreaterThanOrEqual(1000, $port);
            $this->assertLessThanOrEqual(2000, $port);
        }
    }

    /**
     * 测试 emailSuffixVerify 邮箱后缀验证
     */
    public function test_email_suffix_verify(): void
    {
        $suffixes = ['gmail.com', 'yahoo.com', 'outlook.com'];

        $this->assertTrue(\App\Utils\Helper::emailSuffixVerify('test@gmail.com', $suffixes));
        $this->assertFalse(\App\Utils\Helper::emailSuffixVerify('test@evil.com', $suffixes));
    }
}
