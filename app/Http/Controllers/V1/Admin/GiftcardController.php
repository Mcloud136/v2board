<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GiftcardGenerate;
use App\Models\Giftcard;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GiftcardController extends Controller
{
    private function sanitizeCsvCell($value): string
    {
        $value = (string)$value;
        if (preg_match('/^[=+\-@\t\r\n]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    public function fetch(Request $request)
    {
        $current = $request->input('current', 1);
        $pageSize = min(max((int)$request->input('pageSize', 10), 10), 500);
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        // 排序列白名单，非法列回退 id
        $sort = in_array($request->input('sort', 'id'), ['id', 'created_at', 'updated_at', 'expired_at', 'status']) ? $request->input('sort', 'id') : 'id';
        
        $builder = Giftcard::orderBy($sort, $sortType);
        $total = $builder->count();
        $giftcards = $builder->forPage($current, $pageSize)->get();

        return response([
            'data' => $giftcards,
            'total' => $total
        ]);
    }

    public function generate(GiftcardGenerate $request)
    {
        if ($request->input('generate_count')) {
            $this->multiGenerate($request);
            return;
        }

        $params = $request->validated();
        if (!$request->input('id')) {
            if (!isset($params['code'])) {
                $params['code'] = Helper::randomChar(16);
            }
            if (!Giftcard::create($params)) {
                abort(500, '礼品卡创建失败');
            }
        } else {
            $giftcard = Giftcard::find($request->input('id'));
            if (!$giftcard) {
                abort(404, '礼品卡不存在');
            }
            try {
                $giftcard->update($params);
            } catch (\Exception $e) {
                abort(500, '礼品卡保存失败');
            }
        }

        return response([
            'data' => true
        ]);
    }

    private function multiGenerate(GiftcardGenerate $request)
    {
        $giftcards = [];
        $giftcard = $request->validated();
        $giftcard['created_at'] = $giftcard['updated_at'] = time();
        unset($giftcard['generate_count']);
        
        for ($i = 0; $i < $request->input('generate_count'); $i++) {
            do {
                $giftcard['code'] = Helper::randomChar(16);
            } while (Giftcard::where('code', $giftcard['code'])->exists());
            array_push($giftcards, $giftcard);
        }
        DB::beginTransaction();
        try {
            if (!Giftcard::insert($giftcards)) {
                throw new \Exception('礼品卡批量生成失败');
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getmessage()); abort(500, '操作失败，请稍后重试');
        }
        $giftcardvalue = $giftcard['value'] ?? 0;
        $data = "名称,类型,数值,开始时间,结束时间,可用次数,礼品卡卡密,生成时间\r\n";
        foreach ($giftcards as $giftcard) {
            $type = ['', '金额', '时长', '流量', '重置', '套餐'][$giftcard['type']];
            $value = ['', round($giftcardvalue/100, 2), $giftcardvalue . '天', $giftcardvalue . 'GB', '-', $giftcardvalue . '天'][$giftcard['type']];
            $startTime = date('Y-m-d H:i:s', $giftcard['started_at']);
            $endTime = date('Y-m-d H:i:s', $giftcard['ended_at']);
            $limitUse = $giftcard['limit_use'] ?? '不限制';
            $createTime = date('Y-m-d H:i:s', $giftcard['created_at']);
            $data .= implode(',', [
                $this->sanitizeCsvCell($giftcard['name']),
                $type,
                $value,
                $startTime,
                $endTime,
                $limitUse,
                $giftcard['code'],
                $createTime
            ]) . "\r\n";
        }

        return response("\xEF\xBB\xBF" . $data)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="giftcards_' . date('YmdHis') . '.csv"');
    }

    public function drop(Request $request)
    {
        $giftcardId = $request->input('id');
        if (empty($giftcardId)) {
            abort(400, '未找到礼品卡');
        }

        $giftcard = Giftcard::find($giftcardId);
        if (!$giftcard) {
            abort(404, '礼品卡不存在');
        }

        if (!$giftcard->delete()) {
            abort(500, '删除失败');
        }

        return response([
            'data' => true
        ]);
    }
}
