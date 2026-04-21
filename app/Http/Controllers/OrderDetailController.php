<?php

namespace App\Http\Controllers;

use App\Models\OrderDetail;
use Illuminate\Http\Request;

class OrderDetailController extends Controller
{
    public function index()
    {
        return OrderDetail::with(['order', 'productDetail'])->get();
    }

    public function show($id)
    {
        return OrderDetail::with(['order', 'productDetail'])->findOrFail($id);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'product_detail_id' => 'required|exists:product_details,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|integer|min:0',
        ]);

        return OrderDetail::create($data);
    }

    public function update(Request $request, $id)
    {
        $detail = OrderDetail::findOrFail($id);

        $detail->update($request->validate([
            'quantity' => 'integer|min:1',
            'price' => 'integer|min:0',
        ]));

        return $detail;
    }

    public function destroy($id)
    {
        $detail = OrderDetail::findOrFail($id);
        $detail->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
