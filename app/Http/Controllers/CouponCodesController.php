<?php

namespace App\Http\Controllers;

use App\Models\CouponCode;
use Illuminate\Http\Request;

class CouponCodesController extends Controller
{
    public function show($code, Request $request)
    {
        // 优惠券是否存在
        if (!$record = CouponCode::query()->where('code', $code)->first()) {
            abort(404);
        }

        $record->checkAvailable($request->user());

        return $record;
    }
}
