<?php

namespace App\Http\Controllers;

use App\Models\ProductDetail;
use App\Models\Sizes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SizesController extends Controller
{
    public function index()
    {
        try {
            $sizes = Sizes::orderBy('name')->get(['id', 'name', 'created_at', 'updated_at']);
            return response()->json($sizes);
        } catch (\Throwable $e) {
            Log::error('Sizes index error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

  
    public function show($id)
    {
        try {
            $size = Sizes::findOrFail($id);
            return response()->json($size);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Size không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Size show error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

  
    public function store(Request $request)
    {
    
        Log::info('store Size called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);
        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));
        $payload = $request->all();
        Log::debug('Request payload', $payload);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:sizes,name',
            ]);

            Log::info('Validation passed for Size.store', $validated);

            $size = Sizes::create([
                'name' => $validated['name'],
            ]);

            Log::info('Size created with ID: ' . $size->id, ['size_id' => $size->id]);

            return response()->json($size, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while creating size', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Create Size error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

   
    public function update(Request $request, $id)
    {
     
        Log::info('update Size called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'id' => $id,
        ]);
        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));
        $payload = $request->all();
        Log::debug('Request payload', $payload);

        try {
            $size = Sizes::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:sizes,name,' . $id,
            ]);

            $size->update(['name' => $validated['name']]);

            Log::info('Size updated', ['size_id' => $size->id]);

            return response()->json($size);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while updating size', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Size không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Update Size error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

   
    public function destroy($id)
    {
        Log::info('destroy Size called', ['id' => $id]);

        try {
            $size = Sizes::findOrFail($id);

            // CHẶN: nếu có biến thể dùng size này và quantity > 0
            $hasStock = ProductDetail::where('size_id', $id)
                ->where('quantity', '>', 0)
                ->exists();

            if ($hasStock) {
                Log::warning('[SIZE_DELETE_BLOCKED_BY_STOCK]', [
                    'size_id' => $id,
                    'reason'  => 'product_details.quantity > 0'
                ]);

                return response()->json([
                    'code'    => 'SIZE_DELETE_BLOCKED_BY_STOCK',
                    'message' => 'Không thể xóa size vì đã có biến thể phát sinh tồn kho.'
                ], 409);
            }

            // OK: cho xóa
            $size->delete();

            Log::info('Size deleted', ['size_id' => $id]);
            return response()->json(['message' => 'Đã xóa size thành công.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Size không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Delete Size error: ' . $e->getMessage());
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
