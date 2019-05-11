<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no')->unique()->comment('订单号');
            $table->unsignedInteger('user_id')->comment('用户id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->json('address')->comment('用户订单地址');
            $table->decimal('total_amount', 10, 2)->comment('订单总金额');
            $table->text('remark')->nullable()->comment('订单备注');
            $table->dateTime('paid_at')->nullable()->comment('支付时间');
            $table->string('payment_method')->nullable()->comment('支付方式');
            $table->string('payment_no')->nullable()->comment('支付平台订单号');
            $table->string('refund_status')
                ->default(\App\Models\Order::REFUND_STATUS_PENDING)
                ->comment('退款状态');
            $table->string('refund_no')->nullable()->comment('退款单号');
            $table->unsignedTinyInteger('closed')->default(0)->comment('订单是否关闭');
            $table->unsignedTinyInteger('reviewed')->default(0)->comment('订单是否评价');
            $table->string('ship_status')
                ->default(\App\Models\Order::SHIP_STATUS_PENDING)
                ->comment('物流状态');
            $table->json('ship_data')->nullable()->comment('物流数据');
            $table->json('extra')->nullable()->comment('其他额外数据');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
