<?php
use App\Models\Categories;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ColorsController;
use App\Http\Controllers\SizesController;
use App\Http\Controllers\ProductDetailController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ImageProductController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderDetailController;
use App\Http\Controllers\VnPayController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\DiscountController;

Route::post('/vnpay_create_payment', [App\Http\Controllers\VnPayController::class, 'createPayment']);
Route::get('/vnpay_return', [App\Http\Controllers\VnPayController::class, 'vnpayReturn']);

Route::post('/register', [App\Http\Controllers\UserController::class, 'register']);
Route::post('/login', [App\Http\Controllers\UserController::class, 'login']);
Route::post('/send-otp', [App\Http\Controllers\UserController::class, 'sendOtpResetPassword']);
Route::post('/reset-password', [App\Http\Controllers\UserController::class, 'resetPassword']);

Route::get('/products',        [ProductController::class, 'products']);
Route::get('/products/{id}',   [ProductController::class, 'show']);
Route::post('/products',       [ProductController::class, 'addProduct']);
Route::post('/products/{id}',  [ProductController::class, 'update']);
Route::delete('/products/{id}',[ProductController::class, 'destroy']);

Route::get('/product-details',      [ProductDetailController::class, 'index']);
Route::get('/product-details/{id}', [ProductDetailController::class, 'show']);

Route::get('/colors',       [ColorsController::class, 'index']);
Route::get('/colors/{id}',  [ColorsController::class, 'show']);

Route::get('/sizes',        [SizesController::class, 'index']);
Route::get('/sizes/{id}',   [SizesController::class, 'show']);

Route::get('/categories',         [CategoriesController::class, 'index']);
Route::get('/categories/{id}',    [CategoriesController::class, 'show']);

Route::get('/products/{productId}/reviews', [ReviewController::class, 'index']);

Route::post('/discounts/apply', [DiscountController::class, 'apply']);
Route::get('/image-products',         [ImageProductController::class, 'index']);
Route::get('/image-products/{id}',    [ImageProductController::class, 'show']);

Route::middleware('auth:api')->group(function () {
    
    Route::post('/logout',  [UserController::class, 'logout']);
    Route::post('/refresh', [UserController::class, 'refresh']);
    Route::get('/me',       [UserController::class, 'me']);
    Route::put('/me',       [UserController::class, 'updateMe']);
    Route::put('/change-password', [UserController::class, 'changePassword']);

    Route::post('/colors',       [ColorsController::class, 'store']);
    Route::put('/colors/{id}',   [ColorsController::class, 'update']);
    Route::delete('/colors/{id}',[ColorsController::class, 'destroy']);

    Route::post('/sizes',       [SizesController::class, 'store']);
    Route::put('/sizes/{id}',   [SizesController::class, 'update']);
    Route::delete('/sizes/{id}',[SizesController::class, 'destroy']);

    Route::post('/categories',              [CategoriesController::class, 'store']);
    Route::put('/categories/{id}',          [CategoriesController::class, 'update']);
    Route::patch('/categories/{id}',        [CategoriesController::class, 'update']);
    Route::delete('/categories/{id}',       [CategoriesController::class, 'destroy']);

    Route::post('/product-details',          [ProductDetailController::class, 'store']);
    Route::put('/product-details/{id}',      [ProductDetailController::class, 'update']);
    Route::patch('/product-details/{id}',    [ProductDetailController::class, 'update']);
    Route::delete('/product-details/{id}',   [ProductDetailController::class, 'destroy']);

    
    Route::match(['put','patch','post'], '/image-products/{id}', [ImageProductController::class, 'update']);
    Route::delete('/image-products/{id}', [ImageProductController::class, 'destroy']);
    Route::post('/image-products',        [ImageProductController::class, 'store']);

    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::get('/orders',      [OrderController::class, 'index']);
    Route::post('/orders',     [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
    Route::get('/orders-all', [OrderController::class, 'getAll']);

    Route::apiResource('order-details', OrderDetailController::class);

    Route::get('/products/{productId}/my-review', [ReviewController::class, 'myReview']);
    Route::post('/products/{productId}/reviews', [ReviewController::class, 'store']);
    Route::patch('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
});

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::get('/users',  [UserController::class, 'getAll']);
    Route::post('/users', [UserController::class, 'createByAdmin']);
    
    Route::get('/products',        [ProductController::class, 'products']);
    Route::post('/products',       [ProductController::class, 'addProduct']);
    Route::post('/products/{id}',  [ProductController::class, 'update']);
    Route::delete('/products/{id}',[ProductController::class, 'destroy']);
    Route::get('/products/{id}',   [ProductController::class, 'show']);

    Route::apiResource('suppliers', SupplierController::class);

    Route::get('/receipts',           [ReceiptController::class, 'index']);
    Route::post('/receipts',          [ReceiptController::class, 'store']);
    Route::get('/receipts/{receipt}', [ReceiptController::class, 'show']);
    Route::delete('/receipts/{receipt}', [ReceiptController::class, 'destroy']);
    
    Route::get('/inventory/logs',               [InventoryController::class, 'index']);
    Route::post('/inventory/adjust',            [InventoryController::class, 'adjust']);
    Route::post('/inventory/logs',              [InventoryController::class, 'createLogOnly']);
    Route::post('/inventory/revert-receipt/{receiptId}', [InventoryController::class, 'revertReceipt']);

    Route::get('/orders',        [OrderController::class, 'index']);
    Route::put('/orders/{id}',   [OrderController::class, 'update']);
    Route::delete('/orders/{id}',[OrderController::class, 'destroy']);

    Route::get('/reviews', [ReviewController::class, 'adminIndex']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'adminDestroy']);

    Route::get('/discounts',        [DiscountController::class, 'index']);
    Route::post('/discounts',       [DiscountController::class, 'store']);
    Route::get('/discounts/{id}',   [DiscountController::class, 'show']);
    Route::put('/discounts/{id}',   [DiscountController::class, 'update']);
    Route::delete('/discounts/{id}',[DiscountController::class, 'destroy']);
});
