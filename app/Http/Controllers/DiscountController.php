<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;


class DiscountController extends Controller
{
 
    public function index(Request $request)
    {
        $q = Discount::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($qq) use ($s) {
                $qq->where('code', 'like', "%{$s}%")
                   ->orWhere('name', 'like', "%{$s}%");
            });
        }

        if ($request->filled('is_active')) {
            $q->where('is_active', (bool)$request->is_active);
        }

        $discounts = $q->orderByDesc('id')->paginate(10);

        return response()->json($discounts);
    }

   
    public function store(Request $request)
{
    $data = $request->validate([
        'code'        => ['required','string','max:255','unique:discounts,code'],
        'name'        => ['nullable','string','max:255'],
        'type'        => ['required', Rule::in(['percent','fixed'])],
        'value'       => ['required','numeric','min:0.01'],
        'min_total'   => ['nullable','numeric','min:0'],
        'usage_limit' => ['nullable','integer','min:1'],
        'is_active'   => ['nullable','boolean'],
        'start_at'    => ['nullable','date'],
        'end_at'      => ['nullable','date','after_or_equal:start_at'],
    ]);

    if ($data['type'] === 'percent' && $data['value'] > 100) {
        return response()->json(['message' => 'Giảm theo % thì value không được > 100'], 422);
    }

    $discount = Discount::create([
        'code'        => $data['code'],
        'name'        => $data['name'] ?? null,
        'type'        => $data['type'],
        'value'       => $data['value'],
        'min_total'   => $data['min_total'] ?? null,
        'usage_limit' => $data['usage_limit'] ?? null,
        'is_active'   => $data['is_active'] ?? true,
        'start_at'    => $data['start_at'] ?? null,
        'end_at'      => $data['end_at'] ?? null,
    ]);

    return response()->json($discount, 201);
}


    public function show(Discount $discount)
    {
        return response()->json($discount);
    }

    public function update(Request $request, Discount $discount)
    {
        $data = $request->validate([
            'code'        => ['sometimes', 'required', 'string', 'max:255', Rule::unique('discounts', 'code')->ignore($discount->id)],
            'name'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'type'        => ['sometimes', 'required', Rule::in(['percent', 'fixed'])],
            'value'       => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'min_total'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active'   => ['sometimes', 'boolean'],
            'start_at'    => ['sometimes', 'nullable', 'date'],
            'end_at'      => ['sometimes', 'nullable', 'date'],
        ]);

        $start = $data['start_at'] ?? $discount->start_at;
        $end   = $data['end_at'] ?? $discount->end_at;
        if ($start && $end && strtotime($end) < strtotime($start)) {
            return response()->json([
                'message' => 'end_at phải lớn hơn hoặc bằng start_at'
            ], 422);
        }

        $type = $data['type'] ?? $discount->type;
        $value = $data['value'] ?? $discount->value;
        if ($type === 'percent' && $value > 100) {
            return response()->json([
                'message' => 'Giảm theo % thì value không được > 100'
            ], 422);
        }

        $discount->update($data);

        return response()->json([
            'message' => 'Cập nhật thành công',
            'data' => $discount->fresh()
        ]);
    }

    public function destroy(Discount $discount)
    {
        $discount->delete();

        return response()->json([
            'message' => 'Xóa thành công'
        ]);
    }

    public function apply(Request $request)
{
    $data = $request->validate([
        'code'  => ['required', 'string'],
        'total' => ['required', 'numeric', 'min:0'],
    ]);

    $code  = strtoupper(trim($data['code']));
    $total = (float) $data['total'];

    $discount = Discount::where('code', $code)->first();
    if (!$discount) {
        return response()->json(['message' => 'Mã giảm giá không tồn tại'], 404);
    }

    if (!$discount->is_active) {
        return response()->json(['message' => 'Mã giảm giá đang tắt'], 422);
    }

    $now = Carbon::now();

    if ($discount->start_at && $now->lt(Carbon::parse($discount->start_at))) {
        return response()->json(['message' => 'Mã giảm giá chưa đến thời gian áp dụng'], 422);
    }

    if ($discount->end_at && $now->gt(Carbon::parse($discount->end_at))) {
        return response()->json(['message' => 'Mã giảm giá đã hết hạn'], 422);
    }

    if ($discount->min_total && $total < (float) $discount->min_total) {
        return response()->json([
            'message' => 'Đơn hàng chưa đạt giá trị tối thiểu để áp mã',
            'min_total' => (float) $discount->min_total,
        ], 422);
    }

    if (!is_null($discount->usage_limit) && $discount->usage_count >= $discount->usage_limit) {
        return response()->json(['message' => 'Mã giảm giá đã hết lượt sử dụng'], 422);
    }

    $value = (float) $discount->value;
    $amountDiscount = 0;

    if ($discount->type === 'percent') {
        $amountDiscount = $total * ($value / 100);
    } else { 
        $amountDiscount = $value;
    }

    $amountDiscount = max(0, min($amountDiscount, $total));
    $totalAfter = max(0, $total - $amountDiscount);

    return response()->json([
        'amount_discount' => round($amountDiscount, 2),
        'total_after_discount' => round($totalAfter, 2),
        'discount' => [
            'id' => $discount->id,
            'code' => $discount->code,
            'type' => $discount->type,
            'value' => (float) $discount->value,
        ],
    ]);
}


    public function toggle(Discount $discount)
    {
        $discount->is_active = !$discount->is_active;
        $discount->save();

        return response()->json([
            'message' => 'Cập nhật trạng thái thành công',
            'data' => $discount
        ]);
    }
    

}
