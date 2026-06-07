<?php

namespace App\Services;

use App\Models\ServerHysteria;
use App\Models\ServerLog;
use App\Models\ServerRoute;
use App\Models\ServerShadowsocks;
use App\Models\ServerVless;
use App\Models\ServerV2node;
use App\Models\User;
use App\Models\ServerVmess;
use App\Models\ServerTrojan;
use App\Models\ServerTuic;
use App\Models\ServerAnytls;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Support\Facades\Cache;

class ServerService
{
    private const SERVER_MODELS = [
        'shadowsocks' => ServerShadowsocks::class,
        'vmess'       => ServerVmess::class,
        'trojan'      => ServerTrojan::class,
        'tuic'        => ServerTuic::class,
        'hysteria'    => ServerHysteria::class,
        'vless'       => ServerVless::class,
        'anytls'      => ServerAnytls::class,
        'v2node'      => ServerV2node::class,
    ];

    // ─── 通用：获取用户可用服务器 ───────────────────────────────

    private function getAvailableServersByType(string $type, User $user, ?callable $postProcess = null): array
    {
        $modelClass = self::SERVER_MODELS[$type];
        $servers = $modelClass::orderBy('sort', 'ASC')->get();
        $result = [];
        $cacheType = strtoupper($type);

        foreach ($servers as $key => $v) {
            if (!$v['show']) continue;
            $v['type'] = $type;

            if (!in_array($user->group_id, $v['group_id'])) continue;

            if (strpos($v['port'], '-') !== false) {
                $v['port'] = Helper::randomPort($v['port']);
            }

            $checkId = $v['parent_id'] ?? $v['id'];
            $v['last_check_at'] = Cache::get(CacheKey::get("SERVER_{$cacheType}_LAST_CHECK_AT", $checkId));

            if ($postProcess) {
                $postProcess($v, $servers);
            }

            $result[] = $v->toArray();
        }
        return $result;
    }

    public function getAvailableVless(User $user): array
    {
        return $this->getAvailableServersByType('vless', $user, function ($v) {
            if (isset($v['tls_settings'])) {
                $v['tls_settings'] = array_diff_key(
                    $v['tls_settings'],
                    array_flip(['private_key', 'ech_key'])
                );
            }
            if (isset($v['encryption_settings']['private_key'])) {
                $v['encryption_settings'] = array_diff_key($v['encryption_settings'], ['private_key' => '']);
            }
        });
    }

    public function getAvailableVmess(User $user): array
    {
        return $this->getAvailableServersByType('vmess', $user);
    }

    public function getAvailableTrojan(User $user): array
    {
        return $this->getAvailableServersByType('trojan', $user);
    }

    public function getAvailableTuic(User $user)
    {
        return $this->getAvailableServersByType('tuic', $user, function ($v, $all) {
            if (isset($v['parent_id']) && isset($all[$v['parent_id']])) {
                $v['last_check_at'] = Cache::get(CacheKey::get('SERVER_TUIC_LAST_CHECK_AT', $v['parent_id']));
                $v['created_at'] = $all[$v['parent_id']]['created_at'];
            }
        });
    }

    public function getAvailableHysteria(User $user)
    {
        return $this->getAvailableServersByType('hysteria', $user, function ($v, $all) {
            if (isset($v['parent_id']) && isset($all[$v['parent_id']])) {
                $v['last_check_at'] = Cache::get(CacheKey::get('SERVER_HYSTERIA_LAST_CHECK_AT', $v['parent_id']));
                $v['created_at'] = $all[$v['parent_id']]['created_at'];
            }
            $v['server_key'] = Helper::getServerKey($v['created_at'], 16);
        });
    }

    public function getAvailableShadowsocks(User $user)
    {
        return $this->getAvailableServersByType('shadowsocks', $user, function ($v, $all) {
            if (isset($v['parent_id']) && isset($all[$v['parent_id']])) {
                $v['last_check_at'] = Cache::get(CacheKey::get('SERVER_SHADOWSOCKS_LAST_CHECK_AT', $v['parent_id']));
                $v['created_at'] = $all[$v['parent_id']]['created_at'];
            }
            if ($v['obfs'] === 'http') {
                $v['obfs'] = 'http';
                $v['obfs-host'] = $v['obfs_settings']['host'];
                $v['obfs-path'] = $v['obfs_settings']['path'];
            }
        });
    }

    public function getAvailableAnyTLS(User $user)
    {
        return $this->getAvailableServersByType('anytls', $user, function ($v, $all) {
            if (isset($v['parent_id']) && isset($all[$v['parent_id']])) {
                $v['last_check_at'] = Cache::get(CacheKey::get('SERVER_ANYTLS_LAST_CHECK_AT', $v['parent_id']));
                $v['created_at'] = $all[$v['parent_id']]['created_at'];
            }
        });
    }

    public function getAvailableV2node(User $user)
    {
        return $this->getAvailableServersByType('v2node', $user, function ($v, $all) {
            if (isset($v['parent_id']) && isset($all[$v['parent_id']])) {
                $v['last_check_at'] = Cache::get(CacheKey::get('SERVER_V2NODE_LAST_CHECK_AT', $v['parent_id']));
                $v['created_at'] = $all[$v['parent_id']]['created_at'];
            }
            if (isset($v['tls_settings'])) {
                $v['tls_settings'] = array_diff_key(
                    $v['tls_settings'],
                    array_flip(['private_key', 'ech_key'])
                );
            }
            if (isset($v['encryption_settings']['private_key'])) {
                $v['encryption_settings'] = array_diff_key($v['encryption_settings'], ['private_key' => '']);
            }
        });
    }

    // ─── 通用：获取所有服务器（管理用） ─────────────────────────

    private function getAllServersByType(string $type, ?callable $postProcess = null): array
    {
        $modelClass = self::SERVER_MODELS[$type];
        $servers = $modelClass::orderBy('sort', 'ASC')->get()->toArray();
        foreach ($servers as $k => $v) {
            $servers[$k]['type'] = $type;
            if ($postProcess) {
                $postProcess($servers[$k], $v);
            }
        }
        return $servers;
    }

    public function getAllShadowsocks()
    {
        return $this->getAllServersByType('shadowsocks');
    }

    public function getAllVMess()
    {
        return $this->getAllServersByType('vmess');
    }

    public function getAllVLess()
    {
        return $this->getAllServersByType('vless');
    }

    public function getAllTrojan()
    {
        return $this->getAllServersByType('trojan');
    }

    public function getAllTuic()
    {
        return $this->getAllServersByType('tuic');
    }

    public function getAllHysteria()
    {
        return $this->getAllServersByType('hysteria');
    }

    public function getAllAnyTLS()
    {
        return $this->getAllServersByType('anytls', function (&$server, $v) {
            if (isset($v['padding_scheme'])) {
                $server['padding_scheme'] = json_encode($v['padding_scheme']);
            }
        });
    }

    public function getAllV2node()
    {
        return $this->getAllServersByType('v2node', function (&$server, $v) {
            if (isset($v['padding_scheme'])) {
                $server['padding_scheme'] = json_encode($v['padding_scheme']);
            }
            $apiHost = config('v2board.server_api_url', config('v2board.app_url'));
            $apiKey = config('v2board.server_token', '');
            $nodeId = (int) $v['id'];
            $server['install_command'] = sprintf(
                'wget -N https://raw.githubusercontent.com/Mcloud136/v2node/master/script/install.sh && bash install.sh --api-host %s --node-id %d --api-key %s',
                escapeshellarg((string) $apiHost),
                $nodeId,
                escapeshellarg((string) $apiKey)
            );
        });
    }

    // ─── 合并与排序 ──────────────────────────────────────────────

    public function getAvailableServers(User $user)
    {
        $servers = array_merge(
            $this->getAvailableShadowsocks($user),
            $this->getAvailableVmess($user),
            $this->getAvailableTrojan($user),
            $this->getAvailableTuic($user),
            $this->getAvailableHysteria($user),
            $this->getAvailableVless($user),
            $this->getAvailableAnyTLS($user),
            $this->getAvailableV2node($user)
        );
        $tmp = array_column($servers, 'sort');
        array_multisort($tmp, SORT_ASC, $servers);
        return array_map(function ($server) {
            if (strpos($server['port'], '-')) {
                $server['mport'] = (string)$server['port'];
            } else {
                $server['port'] = (int)$server['port'];
            }
            $server['is_online'] = (time() - 300 > $server['last_check_at']) ? 0 : 1;
            $server['cache_key'] = "{$server['type']}-{$server['id']}-{$server['updated_at']}-{$server['is_online']}";
            return $server;
        }, $servers);
    }

    private function mergeData(&$servers)
    {
        foreach ($servers as $k => $v) {
            $serverType = strtoupper($v['type']);
            $parentId = $v['parent_id'] ?? $v['id'];
            $servers[$k]['online'] = Cache::get(CacheKey::get("SERVER_{$serverType}_ONLINE_USER", $parentId));
            $servers[$k]['last_check_at'] = Cache::get(CacheKey::get("SERVER_{$serverType}_LAST_CHECK_AT", $parentId));
            $servers[$k]['last_push_at'] = Cache::get(CacheKey::get("SERVER_{$serverType}_LAST_PUSH_AT", $parentId));
            if ((time() - 300) >= $servers[$k]['last_check_at']) {
                $servers[$k]['available_status'] = 0;
            } else if ((time() - 300) >= $servers[$k]['last_push_at']) {
                $servers[$k]['available_status'] = 1;
            } else {
                $servers[$k]['available_status'] = 2;
            }
        }
    }

    public function getAllServers()
    {
        $servers = array_merge(
            $this->getAllShadowsocks(),
            $this->getAllVMess(),
            $this->getAllTrojan(),
            $this->getAllTuic(),
            $this->getAllHysteria(),
            $this->getAllVLess(),
            $this->getAllAnyTLS(),
            $this->getAllV2node()
        );
        $this->mergeData($servers);
        $tmp = array_column($servers, 'sort');
        array_multisort($tmp, SORT_ASC, $servers);
        return $servers;
    }

    // ─── 其他 ────────────────────────────────────────────────────

    public function getAvailableUsers($groupId)
    {
        return User::whereIn('group_id', $groupId)
            ->whereRaw('u + d < transfer_enable')
            ->where(function ($query) {
                $query->where('expired_at', '>=', time())
                    ->orWhere('expired_at', NULL);
            })
            ->where('banned', 0)
            ->select([
                'id',
                'uuid',
                'speed_limit',
                'device_limit'
            ])
            ->get();
    }

    public function log(int $userId, int $serverId, int $u, int $d, float $rate, string $method)
    {
        if (($u + $d) < 10240) return true;
        $timestamp = strtotime(date('Y-m-d'));
        $serverLog = ServerLog::where('log_at', '>=', $timestamp)
            ->where('log_at', '<', $timestamp + 3600)
            ->where('server_id', $serverId)
            ->where('user_id', $userId)
            ->where('rate', $rate)
            ->where('method', $method)
            ->first();
        if ($serverLog) {
            try {
                $serverLog->increment('u', $u);
                $serverLog->increment('d', $d);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        } else {
            $serverLog = new ServerLog();
            $serverLog->user_id = $userId;
            $serverLog->server_id = $serverId;
            $serverLog->u = $u;
            $serverLog->d = $d;
            $serverLog->rate = $rate;
            $serverLog->log_at = $timestamp;
            $serverLog->method = $method;
            return $serverLog->save();
        }
    }

    public function getRoutes(array $routeIds)
    {
        $routeIds = array_map('intval', $routeIds);
        $routes = ServerRoute::select(['id', 'match', 'action', 'action_value'])
            ->whereIn('id', $routeIds)
            ->get()
            ->sortBy(function ($item) use ($routeIds) {
                return array_search($item->id, $routeIds);
            })
            ->values();
        foreach ($routes as $k => $route) {
            $array = json_decode($route->match, true);
            if (is_array($array)) $routes[$k]['match'] = $array;
        }
        return $routes;
    }

    public function getServer($serverId, $serverType)
    {
        $modelClass = self::SERVER_MODELS[$serverType] ?? null;
        if (!$modelClass) return false;
        return $modelClass::find($serverId);
    }
}
