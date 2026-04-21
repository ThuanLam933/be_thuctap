<?php

namespace App\Http\Controllers;

use App\Models\Colors;
use App\Models\ProductDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class ColorsController extends Controller
{
    public function index()
    {
        try {
            $colors = Colors::orderBy('id')->get(['id', 'name', 'created_at', 'updated_at']);
            return response()->json($colors);
        } catch (\Throwable $e) {
            Log::error('Colors index error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

   
    public function show($id)
    {
        try {
            $color = Colors::findOrFail($id);
            return response()->json($color);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Color không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Color show error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

   
    public function store(Request $request)
    {
       
        Log::info('store Color called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);
        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));
        $payload = $request->all();
        Log::debug('Request payload', $payload);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:colors,name',
            ]);

            Log::info('Validation passed for Color.store', $validated);

            $color = Colors::create([
                'name' => $validated['name'],
            ]);

            Log::info('Color created with ID: ' . $color->id, ['color_id' => $color->id]);

            return response()->json($color, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while creating color', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Create Color error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

   
    public function update(Request $request, $id)
    {
        
        Log::info('update Color called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'id' => $id,
        ]);
        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));
        $payload = $request->all();
        Log::debug('Request payload', $payload);

        try {
            $color = Colors::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:colors,name,' . $id,
            ]);

            $color->update(['name' => $validated['name']]);

            Log::info('Color updated', ['color_id' => $color->id]);

            return response()->json($color);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while updating color', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Color không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Update Color error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    
    public function destroy($id)
    {
        Log::info('destroy Color called', ['id' => $id]);

        try {
            $color = Colors::findOrFail($id);

            // 1) CHẶN: đã có biến thể phát sinh tồn kho (quantity > 0)
            $hasStock = ProductDetail::where('color_id', $id)
                ->where('quantity', '>', 0)
                ->exists();

            if ($hasStock) {
                Log::warning('[COLOR_DELETE_BLOCKED_BY_STOCK]', [
                    'color_id' => $id,
                    'reason' => 'product_details.quantity > 0'
                ]);

                return response()->json([
                    'code' => 'COLOR_DELETE_BLOCKED_BY_STOCK',
                    'message' => 'Không thể xóa màu vì đã có biến thể phát sinh tồn kho.'
                ], 409);
            }

            // 2) (TÙY CHỌN - KHUYẾN NGHỊ) CHẶN: màu đang được dùng trong biến thể dù quantity = 0
            // Nếu bạn muốn "chỉ cần chưa có tồn kho thì cho xóa", thì bạn có thể xóa block này.
            $isUsedByVariants = ProductDetail::where('color_id', $id)->exists();
            if ($isUsedByVariants) {
                Log::warning('[COLOR_DELETE_BLOCKED_BY_VARIANTS]', [
                    'color_id' => $id,
                    'reason' => 'product_details exists'
                ]);

                return response()->json([
                    'code' => 'COLOR_DELETE_BLOCKED_BY_VARIANTS',
                    'message' => 'Không thể xóa màu vì đang được sử dụng bởi biến thể sản phẩm.'
                ], 409);
            }

            // OK: cho xóa
            $color->delete();

            Log::info('Color deleted', ['color_id' => $id]);
            return response()->json(['message' => 'Đã xóa màu thành công.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Color không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Delete Color error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
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
