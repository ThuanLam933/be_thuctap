<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product_detail;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Discount;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function getAll(Request $request)
{
    try {
        $user = $request->user();

        if (!$user || ($user->role ?? '') !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $orders = Order::with([
            'user',
            'discount',
            'items.productDetail.product',
            'items.productDetail.color',
            'items.productDetail.size',
            'items.productDetail.images',
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders, 200);
    } catch (\Throwable $e) {
        Log::error('getAll Orders error: ' . $e->getMessage());

        return response()->json(['message' => 'Server error'], 500);
    }
}
    // user
    public function myOrders()
    {
        $user = auth()->user();

        $orders = Order::with([
            "items.productDetail.product",
            "items.productDetail.color",
            "items.productDetail.size",
            "items.productDetail.images",
        ])
        ->where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json($orders);
    }
    // detail
    public function show(Request $request, $id)
{
    $order = Order::with([
        'user',
        'discount',
        'items.productDetail.product',
        'items.productDetail.color',
        'items.productDetail.size',
        'items.productDetail.images',
    ])->find($id);

    if (!$order) {
        return response()->json(['message' => 'Order not found'], 404);
    }

    $user = $request->user();
    $isAdmin = $user && $user->role === 'admin';

    if (!$isAdmin && $order->user_id !== $user->id) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    foreach ($order->items as $item) {
        $pd = $item->productDetail;

        if ($pd && $pd->images) {
            $pd->images = collect($pd->images)
                ->map(function ($img) {
                    if (preg_match('/^https?:\\/\\//i', $img->url_image)) {
                        $img->full_url = $img->url_image;
                    } else {
                        $img->full_url = url('storage/' . ltrim($img->url_image, '/'));
                    }

                    return $img;
                })
                ->values();
        }
        if ($pd && $pd->product && $pd->product->image_url) {
            if (!preg_match('/^https?:\\/\\//i', $pd->product->image_url)) {
                $pd->product->image_url = url('storage/' . ltrim($pd->product->image_url, '/'));
            }
        }
    }

    return response()->json($order);
}

    public function store(Request $request)
{
    $validator = \Validator::make($request->all(), [
        'customer.name'    => 'required|string',
        'customer.email'   => 'required|email',
        'customer.phone'   => 'required|string',
        'customer.address' => 'required|string',
        'items'                     => 'required|array|min:1',
        'items.*.product_detail_id'  => 'required|integer',
        'items.*.quantity'           => 'required|integer|min:1',
        'payment.method' => ['required', Rule::in(['cod', 'Cash', 'Banking'])],
        'status_method' => ['nullable', 'integer', 'in:0,1'],
        'totals.subtotal'             => 'nullable|numeric|min:0',
        'totals.total_after_discount' => 'nullable|numeric|min:0',
        'totals.total'                => 'nullable|numeric|min:0',
        'discount.amount_discount'    => 'nullable|numeric|min:0',
        'discount.code'               => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ], 422);
    }

    DB::beginTransaction();

    try {
        $payload = $request->all();
        $items   = $payload['items'];
        $user    = auth()->user();
        $total = 0;
        foreach ($items as $it) {
            $unitPrice = (float) ($it['unit_price'] ?? 0);
            $qty       = (int) ($it['quantity'] ?? 1);

            $total += $unitPrice * $qty;
        }
$subtotal = $total;
$finalTotal = $subtotal;

$discountId = $payload['discount_id'] ?? null;

if ($discountId) {
    $discount = Discount::lockForUpdate()->find($discountId);

    if (!$discount || !$discount->is_active) {
        DB::rollBack();
        return response()->json(['message' => 'Mã giảm giá không hợp lệ hoặc đã tắt'], 422);
    }

    $now = now();
    if ($discount->start_at && $now->lt($discount->start_at)) {
        DB::rollBack();
        return response()->json(['message' => 'Mã giảm giá chưa tới thời gian áp dụng'], 422);
    }
    if ($discount->end_at && $now->gt($discount->end_at)) {
        DB::rollBack();
        return response()->json(['message' => 'Mã giảm giá đã hết hạn'], 422);
    }

    if ($discount->min_total != null && $subtotal < $discount->min_total) {
        DB::rollBack();
        return response()->json(['message' => 'Đơn hàng chưa đạt giá trị tối thiểu để áp mã'], 422);
    }

    if ($discount->usage_limit != null && $discount->usage_count >= $discount->usage_limit) {
        DB::rollBack();
        return response()->json(['message' => 'Mã giảm giá đã hết lượt sử dụng'], 422);
    }

    // Tính tiền giảm
    $amountDiscount = 0;
    if ($discount->type === 'percent') {
        $amountDiscount = (int) floor($subtotal * ($discount->value / 100));
    } else { // fixed
        $amountDiscount = (int) $discount->value;
    }

    $finalTotal = max(0, $subtotal - $amountDiscount);

    //  Tăng lượt dùng khi tạo đơn
    $discount->increment('usage_count');
}


        $order = Order::create([
            'user_id'        => $user->id,
            'discount_id'    => $payload['discount_id'] ?? null,
            'order_code'     => Str::uuid(),
            'name'           => $payload['customer']['name'],
            'email'          => $payload['customer']['email'],
            'phone'          => $payload['customer']['phone'],
            'address'        => $payload['customer']['address'],
            'note'           => $payload['note'] ?? '',
            'total_price'    => $finalTotal,
            'payment_method' => $payload['payment']['method'] === 'cod' ? 'Cash' : 'Banking',
            'status_method'  => $payload['payment']['method'] === 'cod' ? 0 : 1,
            'status_stock'   => 1,
            'status'         => 'pending',
        ]);

     
        foreach ($items as $it) {
            $pd = Product_detail::lockForUpdate()->find($it['product_detail_id']);

            if (!$pd) {
                DB::rollBack();
                return response()->json(['message' => 'Sản phẩm hiện tại đang tạm hết'], 422);
            }

            if ($pd->quantity < $it['quantity']) {
                DB::rollBack();
                return response()->json([
                    'message'           => 'Số lượng kho không đủ',
                    'product_detail_id' => $pd->id,
                ], 422);
            }

            OrderDetail::create([
                'order_id'          => $order->id,
                'product_detail_id' => $pd->id,
                'quantity'          => $it['quantity'],
                'price'             => $it['unit_price'], // ✅ GIÁ BÁN (final_price)
            ]);

            $before       = $pd->quantity;
            $pd->quantity -= $it['quantity'];
            $pd->status   = $pd->quantity > 0 ? 1 : 0;
            $pd->save();

            if (class_exists(InventoryLog::class)) {
                InventoryLog::create([
                    'product_detail_id' => $pd->id,
                    'change'            => -$it['quantity'],
                    'quantity_before'   => $before,
                    'quantity_after'    => $pd->quantity,
                    'type'              => 'order',
                    'related_id'         => $order->id,
                    'user_id'            => $user->id,
                    'note'               => "Mua  #{$order->id} ",
                ]);
            }
        }

        DB::commit();

        return response()->json([
            'message' => 'Order created successfully',
            'order'   => $order,
        ], 201);
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Order create error: ' . $e->getMessage());

                return response()->json(['message' => 'Server error'], 500);
            }
        }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || ($user->role ?? '') !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = \Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'shipping', 'completed', 'cancelled', 'canceled'])],
            'payment_method' => ['nullable', Rule::in(['Cash', 'Banking'])],
            'total_price' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        return DB::transaction(function () use ($request, $id, $user) {
            // lock order để tránh race condition
            $order = Order::lockForUpdate()->find($id);
            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            $data = $request->only('status', 'payment_method', 'total_price', 'note');

            $oldStatus = strtolower($order->status ?? '');
            $newStatus = isset($data['status']) ? strtolower($data['status']) : $oldStatus;

            // chặn completed (giống UI)
            if ($oldStatus === 'completed' && $newStatus !== 'completed') {
                return response()->json(['message' => 'Order completed, cannot update'], 422);
            }

            // Nếu đổi sang completed -> set paid
            if (isset($data['status']) && $newStatus === 'completed') {
                $data['status_method'] = 1;
            }

            // ====== HOÀN KHO KHI HUỶ ======
            $cancelStatuses = ['cancelled', 'canceled'];

            // chỉ hoàn kho nếu:
            // - đang chuyển sang trạng thái huỷ
            // - trước đó chưa phải huỷ
            if (isset($data['status']) && in_array($newStatus, $cancelStatuses, true) && !in_array($oldStatus, $cancelStatuses, true)) {

                // lấy items của order
                $details = OrderDetail::where('order_id', $order->id)->get();

                foreach ($details as $d) {
                    $qty = (int)($d->quantity ?? 0);
                    if ($qty <= 0) continue;

                    $pd = Product_detail::lockForUpdate()->find($d->product_detail_id);
                    if (!$pd) continue;

                    $before = (int)($pd->quantity ?? 0);
                    $after  = $before + $qty;

                    $pd->quantity = $after;
                    $pd->status   = $after > 0 ? 1 : 0;
                    $pd->save();

                    InventoryLog::create([
                        'product_detail_id' => $pd->id,
                        'change'            => +$qty,
                        'quantity_before'   => $before,
                        'quantity_after'    => $after,
                        'type'              => 'order_cancel',  // type mới để phân biệt hoàn kho do huỷ
                        'related_id'         => $order->id,
                        'user_id'            => $user->id,
                        'note'               => "Huỷ đơn #{$order->id} hoàn kho",
                    ]);
                }
            }

            $order->update($data);

            return response()->json([
                'message' => 'Order updated',
                'order'   => $order
            ]);
        });
    }



    public function destroy(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['message'=>'Order not found'],404);

        if (($request->user()->role ?? '') !== 'admin') {
            return response()->json(['message'=>'Forbidden'],403);
        }

        $order->delete();
        return response()->json(['message'=>'Order deleted']);
    }
}
