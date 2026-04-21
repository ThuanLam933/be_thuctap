<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 20);
        $query = Supplier::query()->orderBy('id', 'desc');
        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'nullable|email|max:191',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
        ]);

        $supplier = Supplier::create($data);
        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier)
    {
        return $supplier;
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'nullable|email|max:191',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
        ]);

        $supplier->update($data);
        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
