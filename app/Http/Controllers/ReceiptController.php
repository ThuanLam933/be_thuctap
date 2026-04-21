<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\ReceiptDetail;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Product_detail;
use App\Models\InventoryLog;
use App\Models\Product; // update trạng thái product
use Exception;

class ReceiptController extends Controller
{
   
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 20);
        $q = Receipt::with(['supplier', 'user'])->orderBy('import_date', 'desc');

        if ($request->filled('supplier_id')) {
            $q->where('suppliers_id', $request->get('supplier_id'));
        }
        if ($request->filled('date_from')) {
            $q->whereDate('import_date', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->whereDate('import_date', '<=', $request->get('date_to'));
        }

        return $q->paginate($perPage);
    }

   
    public function show(Receipt $receipt)
    {
        $receipt->load(['supplier', 'details.productDetail']);
        return $receipt;
    }

    
    public function store(Request $request)
    {
        $data = $request->validate([
            'suppliers_id' => 'required|exists:suppliers,id',
            'note' => 'nullable|string',
            'import_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_detail_id' => 'required|exists:product_details,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $userId = $request->user() ? $request->user()->id : null;

        DB::beginTransaction();
        try {
          
            $total = 0;
            foreach ($data['items'] as $it) {
                $total += intval($it['quantity']) * floatval($it['price']);
            }

        
            $receipt = Receipt::create([
                'user_id' => $userId,
                'suppliers_id' => $data['suppliers_id'],
                'note' => $data['note'] ?? '',
                'total_price' => $total,
                'import_date' => $data['import_date'] ?? now()->toDateString(),
            ]);

           
            foreach ($data['items'] as $it) {
                $productDetailId = $it['product_detail_id'];
                $qty = intval($it['quantity']);
                $price = floatval($it['price']);
                $subtotal = $qty * $price;

                ReceiptDetail::create([
                    'product_detail_id' => $productDetailId,
                    'receipt_id' => $receipt->id,
                    'quantity' => $qty,
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);

       
                $pd = Product_detail::lockForUpdate()->find($productDetailId);
                if (! $pd) throw new Exception("Product detail id {$productDetailId} not found");

                $beforeQty = (int) ($pd->quantity ?? 0);
                $pd->quantity = $beforeQty + $qty;

                if (array_key_exists('status', $pd->getAttributes()) || property_exists($pd, 'status')) {
                    $pd->status = ($pd->quantity > 0) ? 1 : 0;
                }

                $pd->save();

            
                InventoryLog::create([
                    'product_detail_id' => $pd->id,
                    'change' => $qty,
                    'quantity_before' => $beforeQty,
                    'quantity_after' => (int) $pd->quantity,
                    'type' => 'receipt',
                    'related_id' => $receipt->id,
                    'user_id' => $userId,
                    'note' => "Nhập kho từ phiếu #{$receipt->id}",
                ]);

  
                try {
                    if (!empty($pd->product_id)) {
                        $this->refreshProductStockStatus($pd->product_id);
                    }
                } catch (\Throwable $e) {
                    \Log::warning("Failed to update product status after receipt: ".$e->getMessage());
                }
            }

            DB::commit();

            $receipt->load(['supplier', 'details.productDetail']);
            return response()->json($receipt, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Receipt create failed: '.$e->getMessage(), [
                'trace'=>$e->getTraceAsString(),
                'payload' => $data
            ]);
            return response()->json(['message' => 'Tạo phiếu nhập thất bại', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Receipt $receipt)
    {
        DB::beginTransaction();
        try {
            $receipt->details()->delete();
            $receipt->delete();

            DB::commit();
            return response()->json(['message' => 'Deleted']);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Receipt delete failed: '.$e->getMessage());
            return response()->json(['message' => 'Delete failed'], 500);
        }
    }

    protected function refreshProductStockStatus($productId)
    {
        if (empty($productId)) return;

        $hasStock = Product_detail::where('product_id', $productId)->where('quantity', '>', 0)->exists();

        if (class_exists(Product::class)) {
            $prod = Product::find($productId);
            if ($prod && (array_key_exists('status', $prod->getAttributes()) || property_exists($prod, 'status'))) {
                $prod->status = $hasStock ? 1 : 0;
                $prod->save();
            }
        }
    }
}
