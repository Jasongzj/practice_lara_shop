@extends('layouts.app')
@section('title', '购物车')

@section('content')
    <div class="row">
        <div class="col-lg-10 offset-lg-1">
            <div class="card">
                <div class="card-header">我的购物车</div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" checked></th>
                            <th>商品信息</th>
                            <th>单价</th>
                            <th>数量</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody class="product_list">
                        @foreach($cartItems as $item)
                            <tr data-id="{{ $item->productSku->id }}">
                                <td>
                                    <input type="checkbox" name="select" value="{{ $item->productSku->id }}" {{ $item->productSku->product->on_sale ? 'checked' : 'disabled' }}>
                                </td>
                                <td class="product_info">
                                    <div class="preview">
                                        <a target="_blank" href="{{ route('products.show', [$item->productSku->product_id]) }}">
                                            <img src="{{ $item->productSku->product->image_url }}">
                                        </a>
                                    </div>
                                    <div @if(!$item->productSku->product->on_sale) class="not_on_sale" @endif>
              <span class="product_title">
                <a target="_blank" href="{{ route('products.show', [$item->productSku->product_id]) }}">{{ $item->productSku->product->title }}</a>
              </span>
                                        <span class="sku_title">{{ $item->productSku->title }}</span>
                                        @if(!$item->productSku->product->on_sale)
                                            <span class="warning">该商品已下架</span>
                                        @endif
                                    </div>
                                </td>
                                <td><span class="price">￥{{ $item->productSku->price }}</span></td>
                                <td>
                                    <input type="text" class="form-control form-control-sm amount" @if(!$item->productSku->product->on_sale) disabled @endif name="amount" value="{{ $item->amount }}">
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-danger btn-remove">移除</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <!-- 收货地址开始 -->
                    <div>
                        <form class="form-horizontal" role="form" id="order-form">
                            <div class="form-group row">
                                <label class="col-form-label col-sm-3 text-md-right">选择收货地址</label>
                                <div class="col-sm-9 col-md-7">
                                    <select class="form-control" name="address">
                                        @foreach($addresses as $address)
                                            <option value="{{ $address->id }}">{{ $address->full_address }} {{ $address->contact_name }} {{ $address->contact_phone }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-form-label col-sm-3 text-md-right">备注</label>
                                <div class="col-sm-9 col-md-7">
                                    <textarea name="remark" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            {{-- 优惠码开始 --}}
                            <div class="form-group row">
                                <label class="col-form-label col-sm-3 text-md-right">优惠码</label>
                                <div class="col-sm-4">
                                    <input type="text" class="form-control" name="coupon_code">
                                    <span class="form-text text-muted" id="coupon_desc"></span>
                                </div>
                                <div class="col-sm-3">
                                    <button type="button" class="btn btn-success" id="btn-check-coupon">检查</button>
                                    <button type="button" class="btn btn-danger" id="btn-cancel-coupon" style="display: none;">取消</button>
                                </div>
                            </div>
                            {{-- 优惠码结束 --}}
                            <div class="form-group">
                                <div class="offset-sm-3 col-sm-3">
                                    <button type="button" class="btn btn-primary btn-create-order">提交订单</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- 收货地址结束 -->
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scriptsAfterJs')
    <script>
        $(document).ready(function () {
            // 监听移除按钮
            $('.btn-remove').click(function () {
                // 获取操作元素的祖先元素中的第一个tr标签里的data-id
                var id = $(this).closest('tr').data('id');
                swal({
                    title: '确认要将该商品移除？',
                    icon: 'warning',
                    buttons: ['取消', '确定'],
                    dangerMode: true,
                })
                    .then(function (willDelete) {
                        if (!willDelete) {
                            return;
                        }
                        axios.delete('/cart/' + id)
                            .then(function () {
                                location.reload();
                            });
                    })
            });

            // 监听全选按钮
            $('#select-all').change(function () {
                // 获取单选框的选中状态
                var checked = $(this).prop('checked');

                // 获取所有的不为disabled 的单选框
                $('input[name=select][type=checkbox]:not([disabled])').each(function () {
                    // 将其勾选状态设为与目标单选框一致
                    $(this).prop('checked', checked);
                })
            });

            // 监听提交按钮事件
            $('.btn-create-order').click(function () {
                // 获取用户选择的地址和备注内容
                var req = {
                    address_id: $('#order-form').find('select[name=address]').val(),
                    items: [],
                    remark: $('#order-form').find('textarea[name=remark]').val(),
                    coupon_code: $('input[name=coupon_code]').val(),
                };

                // 遍历table标签内带有 data-id 属性的 tr 标签
                $('table tr[data-id]').each(function () {
                    var $checkbox = $(this).find('input[name=select][type=checkbox]');

                    // 如果单选框被禁用或没有选中则跳过
                    if ($checkbox.prop('disable') || !$checkbox.prop('checked')) {
                        return;
                    }
                    // 获取当前行中数量输入框
                    var $input = $(this).find('input[name=amount]');
                    // 如果输入的数字为0 或 非数字 也跳过
                    if ($input.val() == 0 || isNaN($input.val())) {
                        return;
                    }
                    req.items.push({
                        sku_id: $(this).data('id'),
                        amount: $input.val(),
                    })
                });
                axios.post('{{ route('orders.store') }}', req)
                    .then(function (response) {
                        swal('订单提交成功', '', 'success')
                            .then(function () {
                                location.href = '/orders/' + response.data.id;
                            })
                    }, function (error) {
                        if (error.response.status === 422) {
                            var html = '<div>';
                            _.each(error.response.data.errors, function (errors) {
                                _.each(errors, function (error) {
                                    html += error + '<br>';
                                })
                            });
                            html += '</div>';
                            swal({content: $(html)[0], icon: 'error'});
                        } else if (error.response.status === 403) {
                            swal(error.response.data.msg, '', 'error');
                        } else {
                            swal('系统错误', '', 'error');
                        }
                    });
            });
            // 检查按钮点击事件
            $('#btn-check-coupon').click(function () {
                // 获取输入的优惠码
                var code = $('input[name=coupon_code]').val();
                if (!code) {
                    swal('请输入优惠码', '', 'warning');
                    return;
                }
                axios.get('/coupon_codes/' + encodeURIComponent(code))
                    .then(function (response) {
                        $('#coupon_desc').text(response.data.description);
                        $('input[name=coupon_code]').prop('readonly', true); // 禁用输入框
                        $('#btn-cancel-coupon').show();
                        $('#btn-check-coupon').hide();
                    }, function (error) {
                        if (error.response.status === 404) {
                            swal('该优惠码不存在', '', 'error');
                        } else if (error.response.status === 403) {
                            swal(error.response.data.msg, '', 'error');
                        } else {
                            swal('系统内部错误', '', 'error');
                        }
                    });
            });

            // 优惠券取消按钮事件
            $('#btn-cancel-coupon').click(function () {
                $('#coupon_desc').text('');
                $('input[name=coupon_code]').prop('readonly', false);
                $('#btn-cancel-coupon').hide();
                $('#btn-check-coupon').show();
            })
        })
    </script>
@endsection
