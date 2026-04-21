<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Categories;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\Product_detail;
use App\Models\OrderDetail;
use App\Models\InventoryLog;
use Illuminate\Support\Facades\DB;


class CategoriesController extends Controller
{
    public function index()
    {
        try {
            $categories = Categories::select('id', 'slug', 'name')->get();
            return response()->json($categories);
        } catch (\Throwable $e) {
            Log::error('Categories index error: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'slug' => 'required|string|max:255|unique:categories,slug',
                'name' => 'required|string|max:255',
            ]);

            Log::info('Creating category: ' . $data['name']);

            $category = Categories::create([
                'slug' => $data['slug'],
                'name' => $data['name'],
            ]);

            Log::info('Category created with ID: ' . $category->id);

            return response()->json([
                'message' => 'Tạo category thành công!',
                'category' => $category,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Category store error: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    public function show($id)
    {
        try {
            $category = Categories::find($id);
            if (! $category) {
                return response()->json(['message' => 'Category không tồn tại'], 404);
            }
            return response()->json($category);
        } catch (\Throwable $e) {
            Log::error('Category show error: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'slug' => 'required|string|max:255|unique:categories,slug,' . $id,
                'name' => 'required|string|max:255',
            ]);

            $category = Categories::find($id);
            if (! $category) {
                return response()->json(['message' => 'Category không tồn tại'], 404);
            }

            $category->update($data);

            Log::info('Category updated ID: ' . $category->id);

            return response()->json([
                'message' => 'Cập nhật category thành công!',
                'category' => $category,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Category update error: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $category = Categories::find($id);
            if (!$category) {
                return response()->json(['message' => 'Category không tồn tại'], 404);
            }

            // Lấy danh sách product thuộc category
            $productIds = Product::where('categories_id', $id)->pluck('id');

            // Nếu category chưa có sản phẩm thì xóa OK (hoặc bạn có thể vẫn cấm tùy nghiệp vụ)
            if ($productIds->isEmpty()) {
                $category->delete();

                Log::info("[CATEGORY_DELETE_OK] category_id={$id} no products");
                return response()->json(['message' => 'Xóa category thành công!']);
            }

            // Lấy product_detail ids thuộc các product này
            $pdIds = Product_detail::whereIn('product_id', $productIds)->pluck('id');

            // ===== 1) CHẶN DO ĐƠN HÀNG =====
            if ($pdIds->isNotEmpty()) {
                $hasOrder = OrderDetail::whereIn('product_detail_id', $pdIds)->exists();
                if ($hasOrder) {
                    Log::warning("[CATEGORY_DELETE_BLOCKED_BY_ORDER] category_id={$id} reason=order_details_exists");
                    return response()->json([
                        'code' => 'CATEGORY_DELETE_BLOCKED_BY_ORDER',
                        'message' => 'Không thể xóa loại sản phẩm vì có đơn hàng liên quan.'
                    ], 409);
                }
            }

            // ===== 2) CHẶN DO PHIẾU NHẬP / TỒN KHO BIẾN THỂ =====
            // Cách chặn tối thiểu: chỉ cần biến thể có quantity > 0
            $hasStockQty = Product_detail::whereIn('product_id', $productIds)
                ->where('quantity', '>', 0)
                ->exists();

            if ($hasStockQty) {
                Log::warning("[CATEGORY_DELETE_BLOCKED_BY_INVENTORY] category_id={$id} reason=variant_quantity_gt_0");
                return response()->json([
                    'code' => 'CATEGORY_DELETE_BLOCKED_BY_INVENTORY',
                    'message' => 'Không thể xóa loại sản phẩm vì có biến thể đã phát sinh tồn kho (phiếu nhập).'
                ], 409);
            }

            // Nếu bạn có bảng/log phiếu nhập qua InventoryLog, chặn chặt hơn:
            // (Giả sử log nhập có type = 'import' hoặc tương tự)
            if ($pdIds->isNotEmpty() && class_exists(InventoryLog::class)) {
                $hasImportLog = InventoryLog::whereIn('product_detail_id', $pdIds)
                    ->whereIn('type', ['import', 'receipt', 'stock_in']) // tùy bạn đang dùng type nào
                    ->exists();

                if ($hasImportLog) {
                    Log::warning("[CATEGORY_DELETE_BLOCKED_BY_IMPORT_LOG] category_id={$id} reason=inventory_import_log_exists");
                    return response()->json([
                        'code' => 'CATEGORY_DELETE_BLOCKED_BY_IMPORT_LOG',
                        'message' => 'Không thể xóa loại sản phẩm vì đã có phiếu nhập/tăng kho cho biến thể.'
                    ], 409);
                }
            }

            // ===== Nếu không vướng 2 điều kiện trên =====
            // Khuyến nghị: KHÔNG xóa cứng category nếu đã có sản phẩm,
            // mà chuyển trạng thái "ẩn/ngưng hoạt động" để tránh mất dữ liệu.
            // Nếu bạn vẫn muốn xóa cứng, hãy xóa theo thứ tự trong transaction.
            return DB::transaction(function () use ($category, $productIds, $pdIds, $id) {
                // Nếu muốn xóa cứng:
                // 1) xóa product_details (nếu còn)
                if ($pdIds->isNotEmpty()) {
                    Product_detail::whereIn('id', $pdIds)->delete();
                }

                // 2) xóa products
                Product::whereIn('id', $productIds)->delete();

                // 3) xóa category
                $category->delete();

                Log::info("[CATEGORY_DELETE_OK] category_id={$id} deleted_with_products_no_orders_no_inventory");
                return response()->json(['message' => 'Xóa category thành công!']);
            });

        } catch (\Throwable $e) {
            Log::error('Category delete error: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

}
