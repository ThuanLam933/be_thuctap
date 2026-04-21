<?php

namespace App\Http\Controllers;
use Illuminate\Validation\Rule;
use App\Models\Product_detail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductDetailController extends Controller
{
   
    public function index(Request $request)
    {
        try {

            $query = Product_detail::with(['color', 'size', 'product', 'images'])
                ->orderBy('id', 'desc');

            if ($request->has('product_id') && $request->product_id != "") {
                $query->where('product_id', $request->product_id);
            }

            $details = $query->get([
                'id',
                'product_id',
                'color_id',
                'size_id',
                'price',
                'quantity',
                'status',
                // ✅ ADD
                // 'product_discount_id',
                'created_at',
                'updated_at'
            ]);

            $details->transform(function ($d) {
                if ($d->relationLoaded('images') && $d->images) {
                    $d->images = collect($d->images)->map(function ($img) {
                        $full = $img->full_url ?? $img->url ?? null;
                        if (!$full && !empty($img->url_image)) {
                            $full = preg_match('/^https?:\\/\\//i', $img->url_image)
                                ? $img->url_image
                                : url('storage/' . ltrim($img->url_image, '/'));
                        }
                        $img->full_url = $full;
                        return $img;
                    })->values()->toArray();
                } else {
                    $d->images = [];
                }

                if ($d->product && !empty($d->product->image_url)) {
                    if (!preg_match('/^https?:\\/\\//i', $d->product->image_url)) {
                        $d->product->image_url = url('storage/' . ltrim($d->product->image_url, '/'));
                    }
                }

                // $d->has_discount = false;
                $d->final_price = $d->price;

                // if ($d->discount && method_exists($d->discount, 'isValid') && $d->discount->isValid()) {
                //     $d->has_discount = true;
                //     $d->final_price = $d->discount->applyDiscount($d->price);
                // }

                return $d;
            });

            return response()->json($details);

        } catch (\Throwable $e) {
            Log::error('ProductDetail index error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    public function show($id)
    {
        try {

            $detail = Product_detail::with(['color', 'size', 'product', 'images'])->findOrFail($id);

            if ($detail->relationLoaded('images') && $detail->images) {
                $detail->images = collect($detail->images)->map(function ($img) {
                    $full = $img->full_url ?? $img->url ?? null;
                    if (!$full && !empty($img->url_image)) {
                        $full = preg_match('/^https?:\\/\\//i', $img->url_image)
                            ? $img->url_image
                            : url('storage/' . ltrim($img->url_image, '/'));
                    }
                    $img->full_url = $full;
                    return $img;
                })->values()->toArray();
            } else {
                $detail->images = [];
            }
            if ($detail->product && !empty($detail->product->image_url) && !preg_match('/^https?:\\/\\//i', $detail->product->image_url)) {
                $detail->product->image_url = url('storage/' . ltrim($detail->product->image_url, '/'));
            }
            // $detail->has_discount = false;
            $detail->final_price = $detail->price;

            // if ($detail->discount && method_exists($detail->discount, 'isValid') && $detail->discount->isValid()) {
            //     $detail->has_discount = true;
            //     $detail->final_price = $detail->discount->applyDiscount($detail->price);
            // }

            return response()->json($detail);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Product detail không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('ProductDetail show error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    public function store(Request $request)
    {
        Log::info('store ProductDetail called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);
        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));

        $payload = $request->all();
        Log::debug('Request payload', $payload);

        try {
            $validated = $request->validate([
    'product_id' => ['required', 'exists:products,id'],
    'color_id'   => ['required', 'exists:colors,id'],
    'size_id'    => ['required', 'exists:sizes,id'],

    Rule::unique('product_details')->where(function ($q) use ($request) {
        return $q->where('product_id', $request->product_id)
                 ->where('color_id', $request->color_id)
                 ->where('size_id', $request->size_id);
    }),

    'price'    => ['required', 'numeric', 'min:0'],
    'quantity' => ['sometimes', 'integer', 'min:0'],
    'status'   => ['sometimes', 'boolean'],

    // 'product_discount_id' => ['nullable', 'exists:product_discounts,id'],
], [
    'unique' => 'Biến thể (Màu + Kích cỡ) này đã tồn tại cho sản phẩm.',
]);


            Log::info('Validation passed for ProductDetail.store', $validated);

            $dataToCreate = [
                'product_id' => $validated['product_id'],
                'color_id'   => $validated['color_id'] ?? null,
                'size_id'    => $validated['size_id'] ?? null,
                'price'      => array_key_exists('price', $validated) ? $validated['price'] : null,
                'quantity' => $validated['quantity'] ?? 0,
                'status'     => array_key_exists('status', $validated) ? $validated['status'] : 1,
                // 'product_discount_id' => $validated['product_discount_id'] ?? null,
            ];

            Log::debug('Data to be inserted into product_details table', $dataToCreate);

            $detail = Product_detail::create($dataToCreate);

            Log::info('ProductDetail created with ID: ' . $detail->id, ['id' => $detail->id]);

            return response()->json($detail, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while creating product detail', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Create ProductDetail error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

public function update(Request $request, $id)
{
    Log::info('update ProductDetail called', [
        'path' => $request->path(),
        'method' => $request->method(),
        'ip' => $request->ip(),
        'id' => $id,
    ]);

    $payload = $request->all();
    Log::debug('Request payload', $payload);

    try {
        $detail = Product_detail::findOrFail($id);

        $validated = $request->validate([
    'product_id' => ['required', 'exists:products,id'],
    'color_id'   => ['required', 'exists:colors,id'],
    'size_id'    => ['required', 'exists:sizes,id'],

    // chặn trùng combo, nhưng ignore bản ghi hiện tại
    Rule::unique('product_details')->where(function ($q) use ($request) {
        return $q->where('product_id', $request->product_id)
                 ->where('color_id', $request->color_id)
                 ->where('size_id', $request->size_id);
    })->ignore($id),

    'price'    => ['required', 'numeric', 'min:0'],
    'quantity' => ['sometimes', 'integer', 'min:0'],
    'status'   => ['sometimes', 'boolean'],
    // 'product_discount_id' => ['nullable', 'exists:product_discounts,id'],
], [
    'unique' => 'Biến thể (Màu + Kích cỡ) này đã tồn tại cho sản phẩm.',
]);


        $detail->update($validated);

        return response()->json($detail);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'errors'  => $e->errors(),
        ], 422);
    } catch (\Throwable $e) {
        Log::error('Update ProductDetail error: ' . $e->getMessage());
        return response()->json(['message' => 'Lỗi server'], 500);
    }
}


    public function destroy($id)
    {
        Log::info('destroy ProductDetail called', ['id' => $id]);

        try {
            $detail = Product_detail::findOrFail($id);
            $detail->delete();
            Log::info('ProductDetail deleted', ['id' => $id]);
            return response()->json(['message' => 'Đã xóa product detail thành công.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Product detail không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Delete ProductDetail error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Đang có sản phẩm lên đơn hàng'], 500);
        }
    }

    protected function filterHeadersForLog(array $headers): array
    {
        $sensitive = [
            'authorization',
            'cookie',
            'x-xsrf-token',
            'x-csrf-token',
        ];

        $out = [];
        foreach ($headers as $k => $v) {
            $lk = strtolower($k);
            if (in_array($lk, $sensitive)) {
                $out[$k] = '[REDACTED]';
            } else {
                $out[$k] = is_array($v) && count($v) === 1 ? $v[0] : $v;
            }
        }
        return $out;
    }
}
