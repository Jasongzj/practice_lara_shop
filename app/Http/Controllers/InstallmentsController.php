<?php

namespace App\Http\Controllers;

use App\Events\OrderPaid;
use App\Exceptions\InvalidRequestException;
use App\Models\Installment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstallmentsController extends Controller
{
    public function index(Request $request)
    {
        $installments = Installment::query()
            ->where('user_id', $request->user()->id)
            ->paginate(10);

        return view('installments.index', compact('installments'));
    }

    public function show(Installment $installment)
    {
        $this->authorize('own', $installment);

        $items = $installment->items()->orderBy('sequence')->get();
        return view('installments.show', [
            'installment' => $installment,
            'items' => $items,
            'nextItem' => $items->where('paid_at', null)->first(),
        ]);
    }

    public function payByAlipay(Installment $installment)
    {
        if ($installment->order->closed) {
            throw new InvalidRequestException('对应订单已被关闭');
        }
        if ($installment->status === Installment::STATUS_FINISHED) {
            throw new InvalidRequestException('该分期订单已结清');
        }
        // 获取最近一个未支付的分期计划
        if (!$nextItem = $installment->items()->whereNull('paid_at')->orderBy('sequence')->first()) {
            throw new InvalidRequestException('该分期订单已结清');
        }

        return app('alipay')->web([
            'out_trade_no' => $installment->no . '_' . $nextItem->sequence, // 支付订单号使用分期流水号+还款计划编号
            'total_amount' => $nextItem->total,
            'subject' => '支付 Laravel Shop 的分期订单：' . $installment->no,
            'notify_url' => ngrok_url('installments.alipay.notify'),
            'return_url' => route('installments.alipay.return'),
        ]);
    }

    public function alipayReturn()
    {
        try {
            app('alipay')->verify();
        } catch (\Exception $e) {
            return view('pages.error', ['msg' => '数据不正确']);
        }

        return view('pages.success', ['msg' => '付款成功']);
    }

    public function alipayNotify()
    {
        $data = app('alipay')->verify();
        // 订单如果不是支付成功
        if (!in_array($data->trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return app('alipay')->success();
        }

        // 分期订单号等于订单号+分期计划编号
        list($no, $sequence) = explode('_', $data->out_trade_no);

        if (!$installment = Installment::query()->where('no', $no)->first()) {
            return 'fail';
        }

        if (!$item = $installment->items()->where('sequence', $sequence)->first()) {
            return 'fail';
        }

        if ($item->paid_at) {
            return app('alipay')->success();
        }

        DB::transaction(function () use ($data, $no, $installment, $item) {
            // 更新对应还款计划
            $item->update([
                'paid_at' => Carbon::now(),
                'payment_method' => 'alipay',
                'payment_no' => $data->trade_no, // 支付宝订单号
            ]);

            // 第一期付款
            if ($item->sequence === 0) {
                // 将分期计划改为还款中
                $installment->update(['status' => Installment::STATUS_REPAYING]);
                // 将分期付款订单状态改为已支付
                $installment->order->update([
                    'paid_at' => Carbon::now(),
                    'payment_method' => 'installment',  // 付款方式为分期付款
                    'payment_no' => $no,                // 付款单号为分期付款的流水号
                ]);
                // 触发订单的已支付事件
                event(new OrderPaid($installment->order));
            }

            // 最后一期分期
            if ($item->sequence === $installment->count - 1) {
                // 将分期状态改为已结清
                $installment->update(['status' => Installment::STATUS_FINISHED]);
            }
        });

        return app('alipay')->success();
    }


}
