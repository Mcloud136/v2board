<?php

namespace App\Services;

use App\Jobs\StatServerJob;
use App\Jobs\StatUserJob;
use App\Jobs\TrafficFetchJob;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;

class UserService
{
    private function calcResetDayByMonthFirstDay()
    {
        $today = date('d');
        $lastDay = date('d', strtotime('last day of +0 months'));
        return $lastDay - $today;
    }

    private function calcResetDayByExpireDay(int $expiredAt)
    {
        $day = date('d', $expiredAt);
        $today = date('d');
        $lastDay = date('d', strtotime('last day of +0 months'));
        if ((int)$day >= (int)$today && (int)$day >= (int)$lastDay) {
            return $lastDay - $today;
        }
        if ((int)$day >= (int)$today) {
            return $day - $today;
        }

        return $lastDay - $today + $day;
    }

    private function calcResetDayByYearFirstDay(): int
    {
        $nextYear = strtotime(date("Y-01-01", strtotime('+1 year')));
        return (int)(($nextYear - time()) / 86400);
    }

    private function calcResetDayByYearExpiredAt(int $expiredAt): int
    {
        $md = date('m-d', $expiredAt);
        $nowYear = strtotime(date("Y-{$md}"));
        $nextYear = strtotime('+1 year', $nowYear);
        if ($nowYear > time()) {
            return (int)(($nowYear - time()) / 86400);
        }
        return (int)(($nextYear - time()) / 86400);
    }

    private function getResetTrafficMethod($plan)
    {
        if ($plan->reset_traffic_method === NULL) {
            return (int)config('v2board.reset_traffic_method', 0);
        }
        return (int)$plan->reset_traffic_method;
    }

    public function getResetDay(User $user)
    {
        if (!isset($user->plan)) {
            if ($user->plan_id === NULL) return null;
            $user->plan = Plan::find($user->plan_id);
        }
        if ($user->expired_at <= time() || $user->expired_at === NULL) return null;
        
        $method = $this->getResetTrafficMethod($user->plan);
        if ($method === 2) return null;
        
        $map = [
            0 => 'calcResetDayByMonthFirstDay',
            1 => 'calcResetDayByExpireDay',
            3 => 'calcResetDayByYearFirstDay',
            4 => 'calcResetDayByYearExpiredAt'
        ];
        
        if (!isset($map[$method])) return null;
        
        $func = $map[$method];
        if ($method === 1 || $method === 4) {
            return $this->$func($user->expired_at);
        }
        return $this->$func();
    }

    public function getResetPeriod(User $user)
    {
        if ($user->plan_id === NULL) return null;
        $plan = Plan::find($user->plan_id);
        if ($user->expired_at <= time() || $user->expired_at === NULL) return null;
        
        $method = $this->getResetTrafficMethod($plan);
        if ($method === 2) return null;
        
        $map = [
            0 => 1,
            1 => 30,
            3 => 12,
            4 => 365
        ];
        
        return $map[$method] ?? null;
    }

    public function isAvailable(User $user)
    {
        if (!$user->banned && $user->transfer_enable && ($user->expired_at > time() || $user->expired_at === NULL)) {
            return true;
        }
        return false;
    }

    public function getAvailableUsers()
    {
        return User::whereRaw('u + d < transfer_enable')
            ->where(function ($query) {
                $query->where('expired_at', '>=', time())
                ->orWhereNull('expired_at');
            })
            ->where('banned', 0)
            ->get();
    }

    public function getDeviceLimitedUsers()
    {
        return User::whereRaw('u + d < transfer_enable')
            ->where(function ($query) {
                $query->where('expired_at', '>=', time())
                ->orWhereNull('expired_at');
            })
            ->where('banned', 0)
            ->where('device_limit','>', 0)
            ->select('id')
            ->get();
    }

    public function getUnAvailbaleUsers()
    {
        return User::where(function ($query) {
            $query->where('expired_at', '<', time())
                ->orWhere('expired_at', 0);
        })
            ->where(function ($query) {
            $query->where('plan_id', NULL)
                ->orWhere('transfer_enable', 0);
        })
            ->get();
    }

    public function getUsersByIds($ids)
    {
        return User::whereIn('id', $ids)->get();
    }

    public function getAllUsers()
    {
        return User::all();
    }

    public function addBalance(int $userId, int $balance):bool
    {
        $user = User::lockForUpdate()->find($userId);
        if (!$user) {
            return false;
        }
        $user->balance = $user->balance + $balance;
        if ($user->balance < 0) {
            return false;
        }
        if (!$user->save()) {
            return false;
        }
        return true;
    }

    public function isNotCompleteOrderByUserId(int $userId): bool
    {
        return Order::where('user_id', $userId)
            ->whereIn('status', [0, 1])
            ->exists();
    }

    public function trafficFetch(array $server, string $protocol, array $data)
    {
        TrafficFetchJob::dispatch($data, $server, $protocol);
        StatUserJob::dispatch($data, $server, $protocol, 'd');
        StatServerJob::dispatch($data, $server, $protocol, 'd');
    }

    public static function getMaxId()
    {
        return User::max('id');
    }
}
