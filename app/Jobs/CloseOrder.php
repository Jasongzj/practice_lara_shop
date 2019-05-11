<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CloseOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Order
     */
    protected $order;

    /**
     * CloseOrder constructor.
     * @param Order $order
     * @param $delay
     */
    public function __construct(Order $order, $delay)
    {
        $this->order = $order;
        // 设置延迟的时间，$delay表示延迟多少秒执行
        $this->delay($delay);
        Log::error('开始延迟');

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 订单已支付，直接退出
        if ($this->order->paid_at) {
            return ;
        }

        DB::transaction(function () {
            // 关闭订单
            $this->order->update(['closed' => true]);
            // 返还库存
            foreach ($this->order->items as $item) {
                $item->productSku->addStock($item->amount);
            }
            // 减少优惠券用量
            if ($this->order->couponCode) {
                $this->order->couponCode->decreaseUsed();
            }
        });
    }
}
