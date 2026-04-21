<?php

namespace App\Http\Controllers;

use App\Helpers\ImageHelper;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Product_detail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    
    public function products()
    {
        try {
            $products = Product::with(['details.color','details.size'])
                ->select('id','name','slug','description','status','image_url','categories_id')
                ->orderBy('id','desc')
                ->get()
                ->map(function($p) {
                    $p->image_url = $p->image_url
                ? (preg_match('/^https?:\/\//i', $p->image_url)
                    ? $p->image_url
                    : asset('storage/' . ltrim($p->image_url, '/')))
                : null;
                    if ($p->relationLoaded('details')) {
                        $p->details = $p->details->map(function($d){
                            // nếu detail có đường dẫn ảnh, chuẩn hoá (tuỳ schema)
                            if (isset($d->image_url) && $d->image_url) {
                                $d->image_url = preg_match('/^https?:\/\//i', $d->image_url)
                    ? $d->image_url
                    : asset('storage/' . ltrim($d->image_url, '/'));
                            }
                            return $d;
                        })->toArray();
                    } else {
                        $p->details = [];
                    }

                    $first = $p->details[0] ?? null;
                    $p->first_detail = $first ? (object)[
                        'price' => $first['price'] ?? null,
                        'color' => $first['color'] ?? null,
                        'size'  => $first['size'] ?? null,
                        'quantity' => $first['quantity'] ?? null,
                    ] : null;

                    return $p;
                });

            return response()->json($products);
        } catch (\Throwable $e) {
            Log::error('Products error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }


    public function addProduct(Request $request)
    {
        Log::info('addProduct called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);

        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));
        $payload = $request->except(['image', 'images']);
        Log::debug('Request payload (except image file)', $payload);

        try {
            // Ưu tiên lấy ảnh đầu tiên từ images nếu có, nếu không thì lấy image
            $imageUrl = null;
            if ($request->hasFile('images') && is_array($request->file('images'))) {
                $files = $request->file('images');
                if (count($files) > 0 && $files[0] instanceof UploadedFile && $files[0]->isValid()) {
                    try {
                        $imageUrl = ImageHelper::uploadImage($files[0], 'products');
                        Log::info('Image uploaded to Cloudinary (images[0])', ['image_url' => $imageUrl]);
                    } catch (\Exception $e) {
                        Log::error('Image upload failed (images[0])', ['error' => $e->getMessage()]);
                    }
                }
            } elseif ($request->hasFile('image') && $request->file('image')->isValid()) {
                try {
                    $imageUrl = ImageHelper::uploadImage($request->file('image'), 'products');
                    Log::info('Image uploaded to Cloudinary (image)', ['image_url' => $imageUrl]);
                } catch (\Exception $e) {
                    Log::error('Image upload failed (image)', ['error' => $e->getMessage()]);
                }
            }

            // Gán image_url vào validated nếu có
            if ($imageUrl) {
                $request->merge(['image_url' => $imageUrl]);
            }

            $validated = $request->validate([
                'name'          => 'required|string|max:255',
                // 'name'          => 'required|string|max:255|unique:products,name',
                'slug'          => 'nullable|string|max:255',
                'description'   => 'sometimes|nullable|string',
                'status'        => 'required|boolean',
                'categories_id' => 'required|exists:categories,id',
                // file rules
                'image'         => 'sometimes|file|image|max:5120',
                'images'        => 'sometimes|array',
                'images.*'      => 'file|image|max:5120',
                'image_url'     => 'sometimes|nullable|string|max:2048',
            ]);


            Log::info('Validation passed for addProduct', $validated);

            if (empty($validated['slug'])) {
                $base = Str::slug($validated['name']);
                $slug = $base ?: 'p-' . time();
                $i = 1;
                while (Product::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $validated['slug'] = $slug;
            } else {
                $base = Str::slug($validated['slug']);
                $slug = $base ?: Str::slug($validated['name']);
                $i = 1;
                while (Product::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $validated['slug'] = $slug;
            }

            // Đã xử lý upload ảnh ở trên, không cần lặp lại đoạn này
            $dataToCreate = [
                'name'          => $validated['name'],
                'slug'          => $validated['slug'],
                'description'   => $validated['description'] ?? 'Chưa có mô tả',
                'status'        => $validated['status'],
                'categories_id' => $validated['categories_id'],
                'image_url'     => $validated['image_url'] ?? '',
            ];

            DB::beginTransaction();

            $product = Product::create($dataToCreate);   
            $detailsInput = $request->input('details', []);
            if (is_string($detailsInput) && $detailsInput !== '') {
                $decoded = json_decode($detailsInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $detailsInput = $decoded;
                } else {
                    Log::warning('Invalid JSON in details field', ['raw' => $detailsInput]);
                    $detailsInput = [];
                }
            }
            $topLevelDetail = [
                'color_id' => $request->input('color_id', null),
                'size_id' => $request->input('size_id', null),
                'price' => $request->has('price') ? $request->input('price') : null,
                'quantity' => $request->input('quantity', 0),
            ];
            $hasTopLevelDetail = $topLevelDetail['price'] !== null || $topLevelDetail['color_id'] !== null || $topLevelDetail['size_id'] !== null || ($topLevelDetail['quantity'] !== null && $topLevelDetail['quantity'] > 0);

            if (empty($detailsInput) && $hasTopLevelDetail) {
                $detailsInput[] = $topLevelDetail;
            }
            foreach ($detailsInput as $idx => $dRaw) {
                $d = is_array($dRaw) ? $dRaw : [];

                $v = Validator::make($d, [
                    'price' => 'nullable|numeric',
                    'color_id' => 'nullable|exists:colors,id',
                    'size_id'  => 'nullable|exists:sizes,id',
                    'quantity' => 'nullable|integer',
                    'image_url' => 'sometimes|nullable|string|max:2048',
                ]);

                if ($v->fails()) {
                    DB::rollBack();
                    Log::warning('Detail validation failed', ['index' => $idx, 'errors' => $v->errors()->toArray()]);
                    return response()->json(['message' => 'Validation failed for product details', 'errors' => $v->errors()], 422);
                }

                $detailData = [
                    'price' => array_key_exists('price', $d) ? $d['price'] : null,
                    'color_id' => $d['color_id'] ?? null,
                    'size_id'  => $d['size_id'] ?? null,
                    'quantity' => $d['quantity'] ?? 0,
                    'image_url' => $d['image_url'] ?? null,
                ];
                if (method_exists($product, 'details')) {
                    $product->details()->create($detailData);
                } else {
                    $detailData['product_id'] = $product->id;
                    Product_detail::create($detailData);
                }
            }

            DB::commit();
            $product->load('details.color','details.size');
            $product->image_url = $product->image_url ? asset('storage/' . ltrim($product->image_url, '/')) : null;
            if ($product->relationLoaded('details') && $product->details) {
                $product->details = $product->details->map(function($d){
                    if (isset($d->image_url) && $d->image_url) {
                        $d->image_url = asset('storage/' . ltrim($d->image_url, '/'));
                    }
                    return $d;
                })->toArray();
            } else {
                $product->details = [];
            }

            Log::info('Product created with ID: ' . $product->id, ['product_id' => $product->id]);

            return response()->json([
                'message' => 'Thêm sản phẩm thành công!',
                'product' => $product,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::warning('Validation failed while creating product', $e->errors());
            return response()->json([
                'message' => 'Còn thiếu thông tin',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AddProduct error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }
    public function update(Request $request, $id)
{
    Log::info('updateProduct called', ['id' => $id, 'path' => $request->path(), 'method' => $request->method()]);
    $payload = $request->except(['image', 'images']);
    Log::debug('Update payload (except files)', $payload);

    try {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'slug'          => 'nullable|string|max:255',
            'description'   => 'sometimes|nullable|string',
            'status'        => 'sometimes|required|boolean',
            'categories_id' => 'sometimes|required|exists:categories,id',
            'image'         => 'sometimes|file|image|max:102400',
            'images'        => 'sometimes|array',
            'images.*'      => 'file|image|max:102400',
            'image_url'     => 'sometimes|nullable|string|max:2048',
            // details is intentionally loose; we'll validate each item later
            'details'       => 'sometimes',
            'deleted_detail_ids' => 'sometimes|array',
            'deleted_detail_ids.*' => 'integer',
        ]);

        if (isset($validated['slug']) && $validated['slug'] !== null && $validated['slug'] !== '') {
            $base = Str::slug($validated['slug']);
            $slug = $base ?: Str::slug($validated['name'] ?? $product->name);
            $i = 1;
            while (Product::where('slug', $slug)->where('id', '<>', $product->id)->exists()) {
                $slug = $base . '-' . $i++;
            }
            $validated['slug'] = $slug;
        } elseif (isset($validated['name']) && (!isset($validated['slug']) || $validated['slug'] === '')) {
            $base = Str::slug($validated['name']);
            $slug = $base ?: 'p-' . time();
            $i = 1;
            while (Product::where('slug', $slug)->where('id', '<>', $product->id)->exists()) {
                $slug = $base . '-' . $i++;
            }
            $validated['slug'] = $slug;
        }
        if ($request->hasFile('images') && is_array($request->file('images'))) {
            $files = $request->file('images');
            if (count($files) > 0) {
                $first = $files[0];
                $path = $first->store('products', 'public');
                $validated['image_url'] = $path;
                Log::info('Images[] uploaded (update), first stored', ['path' => $path]);
            }
        } elseif ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = $path;
            Log::info('Single image uploaded (update)', ['path' => $path]);
        }

        DB::beginTransaction();

            $hasNewImage =
                ($request->hasFile('image')) ||
                ($request->hasFile('images') && is_array($request->file('images')) && count($request->file('images')) > 0);
            if (!$hasNewImage) {
                unset($validated['image_url']);
            }
            if (!empty($validated['image_url'])) {
                $validated['image_url'] = preg_replace('#^.*?/storage/#', '', $validated['image_url']);
            }
            $product->fill($validated);
            $product->save();
            $detailsInput = $request->input('details', null);

        if ($detailsInput !== null) {
            if (is_string($detailsInput) && $detailsInput !== '') {
                $decoded = json_decode($detailsInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $detailsInput = $decoded;
                } else {
                    Log::warning('Invalid JSON in details field (update)', ['raw' => $detailsInput]);
                    $detailsInput = [];
                }
            }
            if (!is_array($detailsInput)) {
                $detailsInput = [];
            }

            $processedIds = [];

            foreach ($detailsInput as $idx => $dRaw) {
                $d = is_array($dRaw) ? $dRaw : [];

                $v = Validator::make($d, [
                    'id' => 'sometimes|integer|exists:product_details,id',
                    'price' => 'nullable|numeric',
                    'color_id' => 'nullable|exists:colors,id',
                    'size_id'  => 'nullable|exists:sizes,id',
                    'quantity' => 'nullable|integer',
                    'image_url' => 'sometimes|nullable|string|max:2048',
                ]);

                if ($v->fails()) {
                    DB::rollBack();
                    Log::warning('Detail validation failed (update)', ['index' => $idx, 'errors' => $v->errors()->toArray()]);
                    return response()->json(['message' => 'Validation failed for product details', 'errors' => $v->errors()], 422);
                }

                $detailData = [
                    'price' => array_key_exists('price', $d) ? $d['price'] : null,
                    'color_id' => $d['color_id'] ?? null,
                    'size_id'  => $d['size_id'] ?? null,
                    'quantity' => $d['quantity'] ?? 0,
                    'image_url' => $d['image_url'] ?? null,
                ];
                if (!empty($d['id'])) {
                    $detail = $product->details()->where('id', $d['id'])->first();
                    if ($detail) {
                        $detail->update($detailData);
                        $processedIds[] = $detail->id;
                    } else {
                        Log::warning('Detail id provided but not found for this product (update)', ['detail_id' => $d['id'], 'product_id' => $product->id]);
                    }
                } else {
                    $new = $product->details()->create($detailData);
                    $processedIds[] = $new->id;
                }
            }
            $deleted = $request->input('deleted_detail_ids', []);
            if (is_array($deleted) && count($deleted) > 0) {
                $toDelete = $product->details()->whereIn('id', $deleted)->pluck('id')->toArray();
                if (count($toDelete) > 0) {
                    $product->details()->whereIn('id', $toDelete)->delete();
                }
            }
            if ($request->boolean('replace_details') && count($processedIds) > 0) {
                $product->details()->whereNotIn('id', $processedIds)->delete();
            }
        }

        DB::commit();
        $product->load('details.color','details.size');
        $product->image_url = $product->image_url
    ? (preg_match('/^https?:\/\//i', $product->image_url)
        ? $product->image_url
        : asset('storage/' . ltrim($product->image_url, '/')))
    : null;


        if ($product->relationLoaded('details') && $product->details) {
            $product->details = $product->details->map(function($d){
                if (isset($d->image_url) && $d->image_url) {
                    $d->image_url = asset('storage/' . ltrim($d->image_url, '/'));
                }
                return $d;
            })->toArray();
        } else {
            $product->details = [];
        }

        Log::info('Product updated', ['id' => $product->id]);

        return response()->json(['message' => 'Cập nhật thành công', 'product' => $product]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        Log::warning('Validation failed while updating product', $e->errors());
        return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('UpdateProduct error: ' . $e->getMessage());
        Log::error($e->getTraceAsString());
        Log::error('Last known payload', $payload);
        return response()->json(['message' => 'Lỗi server'], 500);
    }
}

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            if ($product->image_url) {
                try {
                    \Storage::disk('public')->delete($product->image_url);
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete product image from storage', ['msg' => $e->getMessage()]);
                }
            }
            $product->delete();
            Log::info('Product deleted', ['id' => $id]);
            return response()->json(['message' => 'Đã xóa'], 200);
        } catch (\Throwable $e) {
            Log::error('Delete product error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }
    public function show($id)
{
    try {
        $product = Product::with(['details.color','details.size'])
                          ->findOrFail($id);
        if ($product->image_url && !preg_match('/^https?:\/\//', $product->image_url)) {
            $product->image_url = asset('storage/' . ltrim($product->image_url, '/'));
        }
        if ($product->details) {
            $product->details = $product->details->map(function($d){
                if ($d->image_url && !preg_match('/^https?:\/\//', $d->image_url)) {
                    $d->image_url = asset('storage/' . ltrim($d->image_url, '/'));
                }
                return $d;
            });
        }

        return response()->json($product);

    } catch (\Throwable $e) {
        return response()->json(['message' => 'Product not found'], 404);
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
