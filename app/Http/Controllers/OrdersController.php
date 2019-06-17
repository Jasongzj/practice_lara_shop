<?php

namespace App\Http\Controllers;

use App\Events\OrderReviewed;
use App\Exceptions\CouponCodeUnavailableException;
use App\Exceptions\InvalidRequestException;
use App\Http\Requests\ApplyRefundRequest;
use App\Http\Requests\CrowdFundingOrderRequest;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\SeckillOrderRequest;
use App\Http\Requests\SendReviewRequest;
use App\Models\CouponCode;
use App\Models\Order;
use App\Models\ProductSku;
use App\Models\UserAddress;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdersController extends Controller
{
    public function store(OrderRequest $request, OrderService $orderService)
    {
        $user = $request->user();
        $address = UserAddress::find($request->input('address_id'));
        $remark = $request->input('remark');
        $items = $request->input('items');
        $coupon =null;

        if ($couponCode = $request->input('coupon_code')) {
            $coupon = CouponCode::query()->where('code', $couponCode)->first();
            if (!$coupon) {
                throw new CouponCodeUnavailableException('该优惠券不存在');
            }
        }

        $order = $orderService->store($user, $address, $remark, $items, $coupon);

        return $order;
    }

    public function index(Request $request)
    {
        $orders = Order::query()->with(['items.product', 'items.productSku'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate();

        return view('orders.index', ['orders' => $orders]);
    }

    public function show(Order $order)
    {
        $this->authorize('own', $order);

        return view('orders.show', ['order' => $order->load(['items.product', 'items.productSku'])]);
    }

    public function received(Order $order)
    {
        // 校验权限
        $this->authorize('own', $order);

        // 判断订单发货状态是否为已发货
        if ($order->ship_status !== Order::SHIP_STATUS_DELIVERED) {
            throw new InvalidRequestException('发货状态不正确');
        }

        // 更新发货状态
        $order->update(['ship_status' => Order::SHIP_STATUS_RECEIVED]);

        return $order;
    }

    public function review(Order $order)
    {
        $this->authorize('own', $order);

        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可评价');
        }

        return view('orders.review', ['order' => $order->load(['items.productSku', 'items.product'])]);
    }

    public function sendReview(Order $order, SendReviewRequest $request)
    {
        $this->authorize('own', $order);

        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可评价');
        }
        if ($order->reviewed) {
            throw new InvalidRequestException('该订单已评价，不可重复评价');
        }
        $reviews = $request->input('reviews');

        DB::transaction(function () use ($order, $reviews) {
            foreach ($reviews as $review) {
                $orderItem = $order->items()->find($review['id']);

                $orderItem->update([
                    'rating' => $review['rating'],
                    'review' => $review['review'],
                    'reviewed_at' => Carbon::now(),
                ]);
            }

            $order->update(['reviewed' => true]);

            event(new OrderReviewed($order));
        });

        return redirect()->back();
    }

    /**
     * 发起退款
     * @param Order $order
     * @param ApplyRefundRequest $request
     * @return Order
     * @throws InvalidRequestException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function applyRefund(Order $order, ApplyRefundRequest $request)
    {
        $this->authorize('own', $order);

        if (!$order->paid_at) {
            throw new InvalidRequestException('订单未支付,不可支付');
        }

        if ($order->type === Order::TYPE_CROWDFUNDING) {
            throw new InvalidRequestException('众筹订单不支持退款');
        }

        if ($order->refund_status !== Order::REFUND_STATUS_PENDING) {
            throw new InvalidRequestException('该订单已申请过退款，请勿重复申请');
        }

        $extra = $order->extra ?: [];
        $extra['refund_reason'] = $request->input('reason');

        $order->update([
            'refund_status' => Order::REFUND_STATUS_APPLIED,
            'extra' => $extra,
        ]);
        return $order;
    }

    public function crowdfunding(CrowdFundingOrderRequest $request, OrderService $orderService)
    {
        $user = $request->user();
        $sku = ProductSku::query()->find($request->input('sku_id'));
        $address = UserAddress::query()->find($request->input('address_id'));
        $amount = $request->input('amount');

        return $orderService->crowdfunding($user, $address, $sku, $amount);
    }

    public function seckill(SeckillOrderRequest $request, OrderService $orderService)
    {
        $user = $request->user();
        $address = UserAddress::query()->find($request->input('address_id'));
        $sku = ProductSku::query()->find($request->input('sku_id'));

        return $orderService->seckill($user, $address, $sku);
    }
}
