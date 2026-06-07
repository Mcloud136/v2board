<?php

declare(strict_types=1);

namespace App\Payments;

class EPay
{
    private array $config;

    public function __construct($config)
    {
        if (empty($config['url']) || empty($config['pid'])) {
            throw new \InvalidArgumentException('EPay 配置缺少 url 或 pid');
        }

        // V2 RSA 签名：需要 private_key 和 public_key
        if (empty($config['private_key']) || empty($config['public_key'])) {
            throw new \InvalidArgumentException(
                'EPay 已升级为 V2 版本（RSA 签名）。请在管理后台 → 支付配置中更新 EPay：'
                . '1. 删除旧的 key（商户密钥）字段 '
                . '2. 填入 private_key（商户私钥）和 public_key（平台公钥）。'
                . '密钥在易支付商户后台 → 个人资料 → API信息 中生成。'
            );
        }

        $this->config = $config;
    }

    public function form(): array
    {
        return [
            'url' => [
                'label'       => '易支付接口地址',
                'description' => '例如：https://pay.example.com（不带尾部斜杠）',
                'type'        => 'input',
            ],
            'pid' => [
                'label'       => '商户ID (PID)',
                'description' => '',
                'type'        => 'input',
            ],
            'private_key' => [
                'label'       => '商户私钥',
                'description' => '在商户后台生成 RSA 密钥对后获取的私钥（粘贴完整内容）',
                'type'        => 'input',
            ],
            'public_key' => [
                'label'       => '平台公钥',
                'description' => '商户后台显示的平台公钥（粘贴完整内容）',
                'type'        => 'input',
            ],
        ];
    }

    public function pay($order)
    {
        $params = [
            'pid'          => $this->config['pid'],
            'method'       => 'web',
            'type'         => 'alipay',
            'out_trade_no' => $order['trade_no'],
            'notify_url'   => $order['notify_url'],
            'return_url'   => $order['return_url'],
            'name'         => $order['trade_no'],
            'money'        => sprintf('%.2f', $order['total_amount'] / 100),
            'clientip'     => request()->ip() ?? '127.0.0.1',
            'timestamp'    => (string)time(),
        ];

        $params['sign']      = $this->sign($params);
        $params['sign_type'] = 'RSA';

        $url = rtrim($this->config['url'], '/') . '/api/pay/create';

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->asForm()->post($url, $params);
            $result = $response->json();
        } catch (\Exception $e) {
            \Log::error('EPay V2 下单请求失败: ' . $e->getMessage());
            abort(500, '支付网关请求失败');
        }

        if (!isset($result['code']) || $result['code'] !== 0) {
            \Log::error('EPay V2 下单失败', $result);
            abort(500, '支付下单失败: ' . ($result['msg'] ?? '未知错误'));
        }

        // 验签
        if (isset($result['sign']) && !$this->verify($result)) {
            \Log::error('EPay V2 返回签名验证失败', $result);
            abort(500, '支付返回签名验证失败');
        }

        $payType = $result['pay_type'] ?? 'jump';
        $payInfo = $result['pay_info'] ?? '';

        // 根据 pay_type 确定返回类型
        switch ($payType) {
            case 'qrcode':
                // 返回二维码链接，前端生成二维码
                return [
                    'type' => 0, // qrcode
                    'data' => $payInfo,
                ];
            case 'jump':
            case 'html':
            default:
                // 返回跳转 URL
                return [
                    'type' => 1, // url
                    'data' => $payInfo,
                ];
        }
    }

    public function notify($params)
    {
        if (!isset($params['sign'], $params['trade_status'], $params['out_trade_no'])) {
            return false;
        }

        if ($params['trade_status'] !== 'TRADE_SUCCESS') {
            return false;
        }

        if (!$this->verify($params)) {
            \Log::error('EPay V2 回调签名验证失败', $params);
            return false;
        }

        return [
            'trade_no'    => $params['out_trade_no'],
            'callback_no' => $params['trade_no'] ?? '',
        ];
    }

    /**
     * RSA 签名 (SHA256WithRSA)
     */
    private function sign(array $params): string
    {
        $signString = $this->buildSignString($params);

        $privateKey = $this->formatKey($this->config['private_key'], 'PRIVATE');
        $key = openssl_pkey_get_private($privateKey);
        if (!$key) {
            throw new \RuntimeException('EPay: 无效的商户私钥');
        }

        $signature = '';
        openssl_sign($signString, $signature, $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);

        return base64_encode($signature);
    }

    /**
     * RSA 验签 (SHA256WithRSA)
     */
    private function verify(array $params): bool
    {
        $sign = $params['sign'] ?? '';
        unset($params['sign'], $params['sign_type']);

        $signString = $this->buildSignString($params);

        $publicKey = $this->formatKey($this->config['public_key'], 'PUBLIC');
        $key = openssl_pkey_get_public($publicKey);
        if (!$key) {
            \Log::error('EPay: 无效的平台公钥');
            return false;
        }

        $result = openssl_verify($signString, base64_decode($sign), $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);

        return $result === 1;
    }

    /**
     * 构建待签名字符串（按参数名 ASCII 排序，排除 sign/sign_type）
     */
    private function buildSignString(array $params): string
    {
        // 过滤空值和 sign/sign_type
        $filtered = array_filter($params, function ($v, $k) {
            return $k !== 'sign' && $k !== 'sign_type' && $v !== '' && $v !== null;
        }, ARRAY_FILTER_USE_BOTH);

        ksort($filtered);

        $pairs = [];
        foreach ($filtered as $k => $v) {
            $pairs[] = $k . '=' . $v;
        }

        return implode('&', $pairs);
    }

    /**
     * 格式化 PEM 密钥（自动添加头尾）
     */
    private function formatKey(string $key, string $type): string
    {
        $key = trim($key);

        // 如果已经是完整 PEM 格式，直接返回
        if (str_starts_with($key, '-----BEGIN')) {
            return $key;
        }

        // 去除可能的空格和换行
        $key = str_replace(["\r", "\n", ' '], '', $key);

        $header = $type === 'PRIVATE' ? 'RSA PRIVATE KEY' : 'PUBLIC KEY';
        $chunked = chunk_split($key, 64, "\n");

        return "-----BEGIN {$header}-----\n{$chunked}-----END {$header}-----";
    }
}
