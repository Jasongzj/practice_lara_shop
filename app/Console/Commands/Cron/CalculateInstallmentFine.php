<?php

namespace App\Console\Commands\Cron;

use App\Models\Installment;
use App\Models\InstallmentItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CalculateInstallmentFine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:calculate-installment-fine';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '计算分期付款逾期费';


    public function handle()
    {
        InstallmentItem::query()
            ->with(['installment'])
            ->whereHas('installment', function (Builder $query) {
                // 对应分期订单在还款中
                $query->where('status', Installment::STATUS_REPAYING);
            })
            // 还款截止日期在当前时间之前
            ->where('due_date', '<=', Carbon::now())
            // 尚未还款
            ->whereNull('paid_at')
            ->chunkById(1000, function ($items) {
                foreach ($items as $item) {
                    // 计算逾期天数
                    $overdueDays = Carbon::now()->diffInDays($item->due_date);
                    // 本金与手续费之和
                    $base = big_number($item->base)->add($item->fee)->getValue();
                    // 计算逾期费
                    $fine = big_number($base)
                        ->multiply($overdueDays)
                        ->multiply(config('app.installment_fine_rate'))
                        ->divide(100)
                        ->getValue();
                    // 避免逾期费大于本金与手续费之和，如果大于返回1
                    $fine = big_number($fine)->compareTo($base) === 1 ? $base : $fine;
                    $item->update([
                        'fine' => $fine,
                    ]);
                }
            });
    }
}
