<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryLog;
use App\Models\Product_detail;
use App\Models\Receipt;
use App\Models\ReceiptDetail;
use Illuminate\Validation\ValidationException;
use Exception;

class InventoryController extends Controller
{
   
    public function __construct()
    {
        
    }

  
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $q = InventoryLog::query()->with([
            'productDetail',
            'productDetail.product',
            'productDetail.color',
            'productDetail.size',
            'user'
        ]);



        if ($request->filled('product_detail_id')) {
            $q->where('product_detail_id', $request->query('product_detail_id'));
        }
        if ($request->filled('type')) {
            $q->where('type', $request->query('type'));
        }
        if ($request->filled('related_id')) {
            $q->where('related_id', $request->query('related_id'));
        }
        if ($request->filled('date_from')) {
            $q->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->whereDate('created_at', '<=', $request->query('date_to'));
        }
        if ($request->filled('q')) {
            $keyword = trim($request->query('q'));

            $q->where(function ($w) use ($keyword) {
                $w->whereHas('productDetail.product', function ($p) use ($keyword) {
                    $p->where('name', 'like', "%{$keyword}%");
                })
                ->orWhereHas('productDetail.color', function ($c) use ($keyword) {
                    $c->where('name', 'like', "%{$keyword}%");
                })
                ->orWhereHas('productDetail.size', function ($s) use ($keyword) {
                    $s->where('name', 'like', "%{$keyword}%");
                });
                if (is_numeric($keyword)) {
                    $w->orWhere('related_id', (int)$keyword);
                }
            });
        }



        $q->orderByDesc('created_at');


        return response()->json($q->paginate($perPage), 200);
    }

   
    public function adjust(Request $request)
    {
        $data = $request->validate([
            'product_detail_id' => 'required|exists:product_details,id',
            'change' => 'required|integer',
            'note' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $pd = Product_detail::lockForUpdate()->find($data['product_detail_id']);
            if (! $pd) {
                throw new Exception('Product detail not found');
            }

            $before = (int) ($pd->quantity ?? 0);
            $after = $before + intval($data['change']);
            if ($after < 0) {
               
                DB::rollBack();
                return response()->json(['message' => 'Adjustment would produce negative stock'], 422);
            }

            $pd->quantity = $after;
            $pd->save();

            $log = InventoryLog::create([
                'product_detail_id' => $pd->id,
                'change' => intval($data['change']),
                'quantity_before' => $before,
                'quantity_after' => $after,
                'type' => 'adjustment',
                'related_id' => null,
                'user_id' => $request->user() ? $request->user()->id : null,
                'note' => $data['note'] ?? null,
            ]);

            DB::commit();
            return response()->json($log, 201);
        } catch (ValidationException $ve) {
            DB::rollBack();
            return response()->json(['message' => 'Validation error', 'errors' => $ve->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Inventory adjust failed: '.$e->getMessage());
            return response()->json(['message' => 'Adjustment failed', 'error' => $e->getMessage()], 500);
        }
    }

   
    public function revertReceipt(Request $request, $receiptId)
    {
        DB::beginTransaction();
        try {
            $receipt = Receipt::with('details')->find($receiptId);
            if (! $receipt) {
                return response()->json(['message' => 'Receipt not found'], 404);
            }

            $resultLogs = [];
            foreach ($receipt->details as $d) {
                /** @var ReceiptDetail $d */
                $pd = Product_detail::lockForUpdate()->find($d->product_detail_id);
                if (! $pd) {
                    throw new Exception("Product detail {$d->product_detail_id} not found");
                }

                $before = (int) ($pd->quantity ?? 0);
                $after = $before - intval($d->quantity);
                if ($after < 0) {
                    DB::rollBack();
                    return response()->json(['message' => "Không thể hoàn tác hóa đơn sản phẩm đã được bán, chỉnh tay {$pd->id}"], 422);
                }

                $pd->quantity = $after;
                $pd->save();

                $log = InventoryLog::create([
                    'product_detail_id' => $pd->id,
                    'change' => - intval($d->quantity),
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'type' => 'revert_receipt',
                    'related_id' => $receipt->id,
                    'user_id' => $request->user() ? $request->user()->id : null,
                    'note' => "Hoàn tác từ phiếu nhập #{$receipt->id}",
                ]);

                $resultLogs[] = $log;
            }

            DB::commit();
            return response()->json(['message' => 'Receipt reverted', 'logs' => $resultLogs], 200);
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Revert receipt failed: '.$e->getMessage());
            return response()->json(['message' => 'Revert failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function createLogOnly(Request $request)
    {
        $data = $request->validate([
            'product_detail_id' => 'required|exists:product_details,id',
            'change' => 'required|integer',
            'quantity_before' => 'required|integer',
            'quantity_after' => 'required|integer',
            'type' => 'required|string',
            'related_id' => 'nullable|integer',
            'note' => 'nullable|string',
        ]);

        $log = InventoryLog::create([
            'product_detail_id' => $data['product_detail_id'],
            'change' => $data['change'],
            'quantity_before' => $data['quantity_before'],
            'quantity_after' => $data['quantity_after'],
            'type' => $data['type'],
            'related_id' => $data['related_id'] ?? null,
            'user_id' => $request->user() ? $request->user()->id : null,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json($log, 201);
    }
}
