<?php

declare(strict_types=1);

namespace App\Payments;

class EPay
{
    private array $config;

    public function __construct($config)
    {
        if (empty($config['url']) || empty($config['pid']) || empty($config['key'])) {
            throw new \InvalidArgumentException('EPay 配置缺少 url、pid 或 key');
        }

        $this->config = $config;
    }

    public function form(): array
    {
        return [
            'url' => [
                'label'       => '易支付接口地址',
                'description' => '例如：https://pay.example.com（不带 /submit.php）',
                'type'        => 'input',
            ],
            'pid' => [
                'label'       => '商户ID (PID)',
                'description' => '',
                'type'        => 'input',
            ],
            'key' => [
                'label'       => '商户密钥 (KEY)',
                'description' => '',
                'type'        => 'input',
            ],
        ];
    }

    public function pay($order)
    {
        $params = [
            'money'        => sprintf('%.2f', $order['total_amount'] / 100),
            'name'         => $order['trade_no'],
            'notify_url'   => $order['notify_url'],
            'return_url'   => $order['return_url'],
            'out_trade_no' => $order['trade_no'],
            'pid'          => $this->config['pid'],
            'type'         => 'alipay',           // ← 关键修改：强制支付宝直跳
        ];

        $params['sign']      = $this->buildSign($params);
        $params['sign_type'] = 'MD5';

        return [
            'type' => 1, // 1:url
            'data' => $this->config['url'] . '/submit.php?' . http_build_query($params),
        ];
    }

    public function notify($params)
    {
        if (!isset($params['sign'], $params['out_trade_no'], $params['trade_no'])) {
            return false;
        }

        $sign = $params['sign'];
        unset($params['sign'], $params['sign_type']);

        if ($sign !== $this->buildSign($params)) {
            return false;
        }

        return [
            'trade_no'    => $params['out_trade_no'],
            'callback_no' => $params['trade_no'],
        ];
    }

    /**
     * 统一构建易支付签名（已包含 type 参数）
     */
    private function buildSign(array $params): string
    {
        ksort($params);
        $str = stripslashes(urldecode(http_build_query($params))) . $this->config['key'];
        return md5($str);
    }
}