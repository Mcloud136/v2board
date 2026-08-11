<?php

namespace App\Http\Controllers\V1\Admin\Server;

use App\Http\Controllers\Controller;
use App\Services\ServerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManageController extends Controller
{
    public function getNodes(Request $request)
    {
        $serverService = new ServerService();
        return response([
            'data' => $serverService->getAllServers()
        ]);
    }

    public function sort(Request $request)
    {
        ini_set('post_max_size', '5m');
        $params = $request->only(
            'shadowsocks',
            'vmess',
            'vless',
            'trojan',
            'tuic',
            'hysteria',
            'anytls',
            'v2node'
        ) ?? [];
        if (empty($params)) {
            $all = $request->all();
            $params = [
                'shadowsocks' => $all['shadowsocks'] ?? null,
                'vmess'       => $all['vmess'] ?? null,
                'vless'       => $all['vless'] ?? null,
                'trojan'      => $all['trojan'] ?? null,
                'tuic'        => $all['tuic'] ?? null,
                'hysteria'    => $all['hysteria'] ?? null,
                'anytls'      => $all['anytls'] ?? null,
                'v2node'      => $all['v2node'] ?? null,
            ];
        }
        DB::beginTransaction();
        foreach ($params as $k => $v) {
            $model = 'App\\Models\\Server' . ucfirst($k);
            foreach($v as $id => $sort) {
                $server = $model::find($id);
                if (!$server) {
                    DB::rollBack();
                    abort(500, '节点不存在');
                }
                if (!$server->update(['sort' => $sort])) {
                    DB::rollBack();
                    abort(500, '保存失败');
                }
            }
        }
        DB::commit();
        // 排序变更后主动失效节点配置缓存（各协议 Controller 的 save/update/drop 由 60s TTL 自然收敛）
        ServerService::flushServersCache();
        return response([
            'data' => true
        ]);
    }
}
