<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    // GET /api/products/{productId}/reviews?page=1&per_page=6
    public function index(Request $request, $productId)
    {
        $perPage = (int) $request->query('per_page', 6);
        $perPage = max(1, min($perPage, 50));

        // đảm bảo product tồn tại
        $product = Product::findOrFail($productId);

        $query = Review::query()
            ->where('product_id', $product->id)
            ->with(['user:id,name'])
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage);

        $avgRating = (float) Review::where('product_id', $product->id)->avg('rating');
        $count = (int) Review::where('product_id', $product->id)->count();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'avg_rating'   => round($avgRating, 2),
                'review_count' => $count,
            ],
        ]);
    }

    // GET /api/products/{productId}/my-review
    public function myReview(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $review = Review::query()
            ->where('product_id', $product->id)
            ->where('user_id', $request->user()->id)
            ->with(['user:id,name'])
            ->first();

        return response()->json(['data' => $review]);
    }

    // POST /api/products/{productId}/reviews
    public function store(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        
        $data = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string',
            function($attribute,$value,$fail){
                if($value === null || trim($value) === '')return;
                $words = preg_split('/\s+/u', trim($value),-1 ,PREG_SPLIT_NO_EMPTY);
                if(count($words)<5){
                    $fail('sai');
                }
            },
             ],
        ]);

        $existing = Review::query()
            ->where('product_id', $product->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Bạn đã đánh giá sản phẩm này rồi. Hãy cập nhật đánh giá.',
                'data' => $existing,
            ], 409);
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'Date_time_comment' => Carbon::now(),
        ]);

        $review->load(['user:id,name']);

        return response()->json([
            'message' => 'Tạo đánh giá thành công.',
            'data' => $review,
        ], 201);
    }

    
    public function update(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        if ((int) $review->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Không có quyền.'], 403);
        }

        $data = $request->validate([
            'rating'  => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $review->fill($data);
        $review->Date_time_comment = Carbon::now(); 
        $review->save();

        $review->load(['user:id,name']);

        return response()->json([
            'message' => 'Cập nhật đánh giá thành công.',
            'data' => $review,
        ]);
    }

    
    public function destroy(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        if ((int) $review->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Không có quyền.'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Xoá đánh giá thành công.']);
    }
    public function adminIndex(Request $request)
    {
        // Nếu bạn có role admin, nên check ở đây
        $user = $request->user();
        if (($user->role ?? '') !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $q = Review::query()
            ->with([
                'user:id,name,email',
                'product:id,name',
            ])
            ->orderByDesc('id');

        $p = $q->paginate($perPage);

        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
            ],
        ]);
    }

    public function adminDestroy(Request $request, $id)
    {
        $user = $request->user();
        if (($user->role ?? '') !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $review = Review::findOrFail($id);
        $review->delete();

        return response()->json(['message' => 'Xoá đánh giá thành công.']);
    }
}