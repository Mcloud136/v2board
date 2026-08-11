<?php

namespace App\Http\Controllers\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserSendMail;
use App\Http\Requests\Staff\UserUpdate;
use App\Jobs\SendEmailJob;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    private function filter(Request $request, $builder)
    {
        $filters = $request->input('filter');
        $allowedKeys = [
            'email', 'plan_id', 'group_id', 'banned', 'expired_at',
            'transfer_enable', 'd', 'u', 'invite_user_id', 'created_at',
            'last_login_at', 'balance', 'commission_balance', 'is_admin',
            'is_staff', 'token', 'uuid', 'telegram_id'
        ];
        $allowedConditions = ['=', '!=', '>', '<', '>=', '<=', 'like', 'in', 'not in'];
        if ($filters) {
            foreach ($filters as $k => $filter) {
                if (!in_array($filter['key'], $allowedKeys, true)) continue;
                if ($filter['condition'] === '模糊') {
                    $filter['condition'] = 'like';
                    $filter['value'] = "%{$filter['value']}%";
                }
                if (!in_array(strtolower($filter['condition']), $allowedConditions, true)) continue;
                $builder->where($filter['key'], $filter['condition'], $filter['value']);
            }
        }
    }

    public function getUserInfoById(Request $request)
    {
        if (empty($request->input('id'))) {
            abort(500, '参数错误');
        }
        $user = User::where('is_admin', 0)
            ->where('id', $request->input('id'))
            ->where('is_staff', 0)
            ->first();
        if (!$user) abort(500, '用户不存在');
        return response([
            'data' => $user
        ]);
    }

    public function update(UserUpdate $request)
    {
        $params = $request->validated();
        $user = User::find($request->input('id'));
        if (!$user) {
            abort(500, '用户不存在');
        }
        if (User::where('email', $params['email'])->first() && $user->email !== $params['email']) {
            abort(500, '邮箱已被使用');
        }
        if (isset($params['password'])) {
            $params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);
            $params['password_algo'] = NULL;
        } else {
            unset($params['password']);
        }
        if (isset($params['plan_id'])) {
            $plan = Plan::find($params['plan_id']);
            if (!$plan) {
                abort(500, '订阅计划不存在');
            }
            $params['group_id'] = $plan->group_id;
        }

        try {
            $user->update($params);
        } catch (\Exception $e) {
            abort(500, '保存失败');
        }
        return response([
            'data' => true
        ]);
    }

    public function sendMail(UserSendMail $request)
    {
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = in_array($request->input('sort'), ['created_at', 'last_login_at', 'expired_at', 'balance', 'u', 'd', 'transfer_enable', 'id', 'email']) ? $request->input('sort') : 'created_at';
        $builder = User::orderBy($sort, $sortType);
        $this->filter($request, $builder);
        $users = $builder->get();
        foreach ($users as $user) {
            SendEmailJob::dispatch([
                'email' => $user->email,
                'subject' => $request->input('subject'),
                'template_name' => 'notify',
                'template_value' => [
                    'name' => config('v2board.app_name', 'V2Board'),
                    'url' => config('v2board.app_url'),
                    'content' => $request->input('content')
                ]
            ]);
        }

        return response([
            'data' => true
        ]);
    }

    public function ban(Request $request)
    {
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = in_array($request->input('sort'), ['created_at', 'last_login_at', 'expired_at', 'balance', 'u', 'd', 'transfer_enable', 'id', 'email']) ? $request->input('sort') : 'created_at';
        $builder = User::orderBy($sort, $sortType);
        $this->filter($request, $builder);
        try {
            $userIds = $builder->pluck('id')->toArray();
            if (empty($userIds)) {
                return response(['data' => true]);
            }
            User::whereIn('id', $userIds)->update(['banned' => 1]);
            // 清除被封禁用户的 session 缓存（批量 MGET 代替串行往返）
            AuthService::clearUserSessionsBatch($userIds);
        } catch (\Exception $e) {
            abort(500, '处理失败');
        }

        return response([
            'data' => true
        ]);
    }
}
