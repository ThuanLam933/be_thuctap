<?php

namespace App\Http\Controllers;

use App\Models\ImageProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Cloudinary\Cloudinary;

class ImageProductController extends Controller
{
    public function index(Request $request)
    {
        $q = ImageProduct::query();

        if ($request->has('product_detail_id')) {
            $q->where('product_detail_id', $request->input('product_detail_id'));
        }

        $perPage = intval($request->input('per_page', 20));
        $list = $q->orderBy('sort_order', 'asc')->paginate($perPage);

        $list->getCollection()->transform(function ($item) {
            $item->full_url = $item->url; 
            return $item;
        });

        return response()->json($list);
    }

  
    public function store(Request $request)
    {
        
        Log::info('upload diagnostics', [
    'content_length' => $request->server('CONTENT_LENGTH'),
    'post_max_size' => ini_get('post_max_size'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'files' => array_keys($request->allFiles()),
]);

       try {
        $validated = $request->validate([
            'product_detail_id' => ['required', 'integer', 'exists:product_details,id'],
            // File upload (tối đa 5MB). Có thể đổi image -> mimes nếu bạn muốn siết đuôi.
           'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,jfif', 'max:102400'],
            // URL ảnh (nếu không upload file). Dùng url để tránh chuỗi rỗng / không hợp lệ
            'url_image' => ['nullable', 'url'],
            'sort_order' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);
    } catch (ValidationException $e) {
        Log::warning('ImageProductController@store validation failed', [
            'errors' => $e->errors(),
            'hasFile_image' => $request->hasFile('image'),
            'content_type' => $request->header('Content-Type'),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Dữ liệu không hợp lệ',
            'errors'  => $e->errors(),
        ], 422);
    }

    Log::info('ImageProductController@store validated', $validated);

    // 2) Ép điều kiện: phải có file hoặc url_image (sau khi trim)
    $url = trim($validated['url_image'] ?? '');
    $hasFile = $request->hasFile('image');

    if (!$hasFile && $url === '') {
        return response()->json([
            'success' => false,
            'message' => 'Vui lòng cung cấp file ảnh hoặc url_image',
        ], 422);
    }

    // 3) Debug chi tiết file nếu có upload
    if ($hasFile) {
        $file = $request->file('image');

        \Log::info('Image upload debug', [
            'original_name' => $file->getClientOriginalName(),
            'original_ext'  => $file->getClientOriginalExtension(),
            'client_mime'   => $file->getClientMimeType(),
            'mime'          => $file->getMimeType(),
            'size_bytes'    => $file->getSize(),
            'error_code'    => $file->getError(),
            'is_valid'      => $file->isValid(),
        ]);

        if (!$file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'File upload không hợp lệ (PHP upload error)',
                'error_code' => $file->getError(),
            ], 422);
        }
    }

    // 4) Lưu đường dẫn: nếu upload file thì upload Cloudinary, nếu url thì lấy url
    $path = null;

    if ($hasFile) {
        $file = $request->file('image');
        try {
            // Sử dụng ImageHelper để upload lên Cloudinary
            $cloudUrl = \App\Helpers\ImageHelper::uploadImage($file, 'uploads/images');
            $path = $cloudUrl;
            \Log::info('Image uploaded to Cloudinary (ImageProductController)', ['url' => $cloudUrl]);
        } catch (\Exception $e) {
            \Log::error('Cloudinary upload failed in ImageProductController, fallback to local', ['error' => $e->getMessage()]);
            $path = $file->store('uploads/images', 'public');
        }
    } else {
        $path = $url;
    }

    // 5) Tạo record
    $image = ImageProduct::create([
        'product_detail_id' => $validated['product_detail_id'],
        'url_image'         => $path,
        'sort_order'        => $validated['sort_order'] ?? '',
        'description'       => $validated['description'] ?? '',
    ]);

    // 6) Nếu model có accessor getUrlAttribute() thì $image->url sẽ ra full url
    // Gán thêm field trả về cho client (không lưu DB)
    $image->full_url = $image->url ?? null;

    return response()->json([
        'success' => true,
        'image'   => $image,
    ], 201);
    }

    public function show($id)
    {
        $img = ImageProduct::findOrFail($id);
        $img->full_url = $img->url;
        return response()->json($img);
    }

    public function update(Request $request, $id)
    {
        $img = ImageProduct::findOrFail($id);

        $validated = $request->validate([
           // 'image' => ['nullable', 'file', 'image', 'max:15120'],
           'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,jfif', 'max:10240'],


            'url_image' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $newPath = $file->store('uploads/images', 'public');

            if ($img->url_image && !preg_match('/^https?:\\/\\//', $img->url_image)) {
                if (Storage::disk('public')->exists($img->url_image)) {
                    Storage::disk('public')->delete($img->url_image);
                }
            }

            $img->url_image = $newPath;
        } elseif ($request->filled('url_image')) {
            $img->url_image = $validated['url_image'];
        }

        if ($request->filled('sort_order')) {
            $img->sort_order = $validated['sort_order'];
        }

        if ($request->filled('description')) {
            $img->description = $validated['description'];
        }

        $img->save();
        $img->full_url = $img->url;

        return response()->json(['success' => true, 'image' => $img]);
    }

    public function destroy($id)
    {
        $img = ImageProduct::findOrFail($id);

        if ($img->url_image && !preg_match('/^https?:\\/\\//', $img->url_image)) {
            if (Storage::disk('public')->exists($img->url_image)) {
                Storage::disk('public')->delete($img->url_image);
            }
        }

        $img->delete();

        return response()->json(['success' => true]);
    }
}
