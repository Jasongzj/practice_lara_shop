@extends('layouts.app')
@section('title', '查看订单')

@section('content')
<div class="row">
<div class="col-lg-10 offset-lg-1">
<div class="card">
    <div class="card-header">
        <h4>订单详情</h4>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
            <tr>
                <th>商品信息</th>
                <th class="text-center">单价</th>
                <th class="text-center">数量</th>
                <th class="text-right item-amount">小计</th>
            </tr>
            </thead>
            @foreach($order->items as $index => $item)
                <tr>
                    <td class="product-info">
                        <div class="preview">
                            <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">
                                <img src="{{ $item->product->image_url }}">
                            </a>
                        </div>
                        <div>
                            <span class="product-title">
                                <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">{{ $item->product->title }}</a>
                            </span>
                            <span class="sku-title">{{ $item->productSku->title }}</span>
                        </div>
                    </td>
                    <td class="sku-price text-center vertical-middle">￥{{ $item->price }}</td>
                    <td class="sku-amount text-center vertical-middle">{{ $item->amount }}</td>
                    <td class="item-amount text-right vertical-middle">￥{{ number_format($item->price * $item->amount, 2, '.', '') }}</td>
                </tr>
            @endforeach
            <tr><td colspan="4"></td></tr>
        </table>
        <div class="order-bottom">
            <div class="order-info">
                <div class="line">
                    <div class="line-label">收货地址：</div>
                    <div class="line-value">{{ implode(' ', $order->address) }}</div>
                </div>
                <div class="line">
                    <div class="line-label">订单备注：</div>
                    <div class="line-value">{{ $order->remark ?: '-' }}</div>
                </div>
                <div class="line">
                    <div class="line-label">订单编号：</div>
                    <div class="line-value">{{ $order->no }}</div>
                </div>
                <div class="line">
                    <div class="line-label">物流状态：</div>
                    <div class="line-value">{{ \App\Models\Order::$shipStatusMap[$order->ship_status] }}</div>
                </div>
                @if($order->ship_data)
                <div class="line">
                    <div class="line-label">物流信息：</div>
                    <div class="line-value">{{ $order->ship_data['express_company'] }}  {{ $order->ship_data['express_no'] }}</div>
                </div>
                @endif
                @if($order->paid_at && $order->refund_status !== \App\Models\Order::REFUND_STATUS_PENDING)
                    <div class="line">
                        <div class="line-label">退款状态：</div>
                        <div class="line-value">{{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}</div>
                    </div>
                    <div class="line">
                        <div class="line-label">退款理由：</div>
                        <div class="line-value">{{ $order->extra['refund_reason'] }}</div>
                    </div>
                @endif
            </div>
            <div class="order-summary text-right">
                <!-- 展示优惠信息开始 -->
                @if($order->couponCode)
                <div class="text-primary">
                    <span>优惠信息：</span>
                    <div class="value">{{ $order->couponCode->description }}</div>
                </div>
                @endif
            <!-- 展示优惠信息结束 -->
                <div class="total-amount">
                    <span>订单总价：</span>
                    <div class="value">￥{{ $order->total_amount }}</div>
                </div>
                <div>
                    <span>订单状态：</span>
                    <div class="value">
                        @if($order->paid_at)
                            @if($order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING)
                                已支付
                            @else
                                {{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}
                            @endif
                        @elseif($order->closed)
                            已关闭
                        @else
                            未支付
                        @endif
                    </div>
                </div>
                @if(isset($order->extra['refund_disagree_reason']))
                    <div>
                        <span>拒绝退款理由：</span>
                        <div class="value">{{ $order->extra['refund_disagree_reason'] }}</div>
                    </div>
                @endif
                @if(!$order->paid_at && !$order->closed)
                    <div class="payment-buttons">
                        <a href="{{ route('payment.alipay', ['order' => $order->id]) }}" class="btn btn-primary btn-sm">
                            支付宝支付
                        </a>
                        <button id="btn-wechat" class="btn btn-primary btn-sm">
                            微信支付
                        </button>
                        {{--当订单总金额满足最低分期金额要求时显示按钮--}}
                        @if($order->total_amount >= config('app.min_installment_amount'))
                        <button class="btn btn-danger btn-sm" id="btn-installment">
                            分期付款
                        </button>
                        @endif
                    </div>
                @endif

                {{-- 如果订单状态为已发货则显示确认收货按钮 --}}
                @if($order->ship_status === \App\Models\Order::SHIP_STATUS_DELIVERED)
                <div class="received-button">
                    <button class="btn btn-success btn-sm" type="button" id="btn-received">确认收货</button>
                </div>
                @endif
                @if($order->type !== \App\Models\Order::TYPE_CROWDFUNDING && $order->paid_at && $order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING)
                <div class="refund-button">
                    <button class="btn btn-danger btn-sm" id="btn-apply-refund">申请退款</button>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
</div>
</div>
<!-- 分期弹框开始 -->
<div class="modal fade" id="installment-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">选择分期期数</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-striped text-center">
                    <thead>
                    <tr>
                        <th class="text-center">期数</th>
                        <th class="text-center">费率</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(config('app.installment_fee_rate') as $count => $rate)
                        <tr>
                            <td>{{ $count }}期</td>
                            <td>{{ $rate }}%</td>
                            <td>
                                <button class="btn btn-sm btn-primary btn-select-installment" data-count="{{ $count }}">选择</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
            </div>
        </div>
    </div>
</div>
<!-- 分期弹框结束 -->
@endsection

@section('scriptsAfterJs')
    <script>
        $(document).ready(function () {
            // 微信支付事件
            $('#btn-wechat').click(function () {
                swal({
                    content: $('<img src="{{ route('payment.wechat', ['order' => $order->id]) }}"/>')[0],
                    buttons: ['关闭', '已完成支付'],
                })
                    .then(function (result) {
                        if (result) {
                            location.reload();
                        }
                    })
            });
            $('#btn-received').click(function () {
                swal({
                    title: '确认已经收到商品？',
                    icon: 'warning',
                    dangerMode: true,
                    buttons: ['取消', '确认收货'],
                }).then(function (ret) {
                        if (!ret) {
                            return;
                        }
                        axios.post('{{ route('orders.received', [$order->id]) }}')
                            .then(function () {
                                location.reload();
                            })
                    })
            });
            $('#btn-apply-refund').click(function () {
                swal({
                    text: '请输入退款理由',
                    content: "input",
                }).then(function (input) {
                    if (!input) {
                        swal('退款理由不能为空', '', 'error');
                        return;
                    }
                    axios.post('{{ route('orders.apply_refund', [$order->id]) }}', {reason: input})
                        .then(function () {
                            swal('申请退款成功', '', 'success').then(function () {
                                // 点击确认刷新页面
                                location.reload();
                            })
                        })
                })
            });
            $('#btn-installment').click(function () {
                $('#installment-modal').modal();
            });
            $('.btn-select-installment').click(function () {
                //调用分期接口
                axios.post('{{ route('payment.installment', ['order' => $order->id]) }}', { count: $(this).data('count')})
                    .then(function (response) {
                        location.href = '/installments/' + response.data.id;
                    })
            });
        });
    </script>
@endsection
