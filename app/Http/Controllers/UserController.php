<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\EmailOtp;
class UserController extends Controller
{
   
    protected function ensureAdmin()
    {
        try {
            $user = auth('api')->user() ?? JWTAuth::user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401)->send();
            }

            if (($user->role ?? '') !== 'admin') {
                return response()->json(['message' => 'Forbidden'], 403)->send();
            }

        } catch (\Throwable $e) {
            Log::error('ensureAdmin error: ' . $e->getMessage());
            return response()->json(['message' => 'Server error'], 500)->send();
        }
    }


    public function getAll(Request $request)
    {
        // Kiểm tra quyền admin
        $check = $this->ensureAdmin();
        if ($check) return $check;

        try {
            $users = User::all();
            return response()->json($users, 200);

        } catch (\Throwable $e) {
            Log::error('getAll users error: '.$e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }

public function changePassword(Request $request)
{
    try {
        $user = $request->user() ?? JWTAuth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

    
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

     
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => 'Mật khẩu cũ không đúng'
            ], 400);
        }

      
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Đổi mật khẩu thành công'
        ], 200);

    } catch (\Throwable $e) {
        Log::error('changePassword error: ' . $e->getMessage());
        return response()->json(['message' => 'Server error'], 500);
    }
}


    
    public function updateMe(Request $request)
    {
        try {
            $user = $request->user() ?? JWTAuth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $data = $request->only(['name', 'email', 'phone']);

            $rules = [
                'name'  => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
                'phone' => 'sometimes|string|max:30',
            ];

            $messages = [
                'email.unique' => 'Email đã được sử dụng.',
                'email.email'  => 'Email không hợp lệ.',
            ];

            $validator = Validator::make($data, $rules, $messages);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $user->fill($validator->validated());
            $user->save();

            return response()->json($user, 200);


        } catch (\Throwable $e) {
            Log::error('updateMe error: '.$e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        $emailOtp = EmailOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$emailOtp) {
            return response()->json([
                'status' => false,
                'message' => 'OTP không hợp lệ hoặc đã hết hạn'
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Người dùng không tồn tại'
            ], 404);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        // Xoá OTP sau khi sử dụng
        $emailOtp->delete();

        return response()->json([
            'status' => true,
            'message' => 'Đặt lại mật khẩu thành công'
        ]);
    }
   
    public function register(Request $request)
    {
        try {
            $data = $request->only(['name','phone','email','password']);

            $rules = [
                'name'     => 'required|string|max:255',
                'phone'    => 'nullable|string|max:30',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ];

            $messages = [
                'email.unique' => 'Email đã được sử dụng.',
                'email.email'  => 'Email không hợp lệ.',
            ];

            $validator = Validator::make($data, $rules, $messages);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $v = $validator->validated();

            $user = User::create([
                'name'     => $v['name'],
                'email'    => $v['email'],
                'password' => bcrypt($v['password']),
                'phone'    => $v['phone'] ?? null,
                'role'     => 'user',
                'status'   => 1,
            ]);

            $token = $this->createTokenForUser($user);

            return response()->json([
                'status'       => true,
                'message'      => 'Register success',
                'user'         => $user,
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => $this->getTtlSeconds(),
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Register error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Server error'], 500);
        }
    }



    public function createByAdmin(Request $request)
    {
      
        $check = $this->ensureAdmin();
        if ($check) return $check;

        try {
            $data = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'phone'    => 'nullable|string|max:30',
                'role'     => 'nullable|string|in:user,admin',
                'status'   => 'nullable|integer',
            ]);

            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => bcrypt($data['password']),
                'phone'    => $data['phone'] ?? null,
                'role'     => $data['role'] ?? 'user',
                'status'   => $data['status'] ?? 1,
            ]);

            return response()->json(['message' => 'User created by admin', 'user' => $user], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('createByAdmin error: '.$e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        try {
            $guard = auth('api');
            $token = $guard->attempt($credentials) ?: JWTAuth::attempt($credentials);

            if (!$token) {
                return response()->json(['message' => 'Email hoặc mật khẩu không đúng'], 401);
            }

            return $this->respondWithToken($token);

        } catch (JWTException $e) {
            Log::error('JWT Exception on login: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi khi tạo token'], 500);
        } catch (\Throwable $e) {
            Log::error('Login error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }


 
    public function logout(Request $request)
    {
        try {
            $guard = auth('api');
            if (method_exists($guard, 'logout')) {
                $guard->logout();
            } else {
                $token = JWTAuth::getToken();
                if ($token) JWTAuth::invalidate($token);
            }

            return response()->json(['message' => 'Đã đăng xuất']);

        } catch (JWTException $e) {
            Log::error('JWT logout error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi khi logout'], 500);

        } catch (\Throwable $e) {
            Log::error('Logout error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }


    public function refresh()
    {
        try {
            $guard = auth('api');
            $newToken = $guard->refresh() ?? JWTAuth::refresh();
            return $this->respondWithToken($newToken);

        } catch (JWTException $e) {
            Log::error('JWT refresh error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi khi refresh token'], 500);
        } catch (\Throwable $e) {
            Log::error('Refresh error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }


  
    public function me(Request $request)
    {
        try {
            $user = $request->user() ?? JWTAuth::user();
            return response()->json($user);

        } catch (\Throwable $e) {
            Log::error('Me error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }




    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $this->getTtlSeconds(),
            'user'         => $this->meUserForResponse(),
        ]);
    }


    protected function createTokenForUser(User $user)
    {
        $guard = auth('api');
        return $guard->login($user) ?: JWTAuth::fromUser($user);
    }


    protected function meUserForResponse()
    {
        $guard = auth('api');
        return $guard->user() ?? JWTAuth::user();
    }


    protected function getTtlSeconds()
    {
        try {
            $guard = auth('api');
            return $guard->factory()->getTTL() * 60;
        } catch (\Throwable $e) {
            return 3600; // fallback 1h
        }
    }
    public function sendOtpResetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Email không tồn tại'
            ], 404);
        }

        $otp = rand(100000, 999999);
        EmailOtp::updateOrCreate(
            ['email' => $request->email],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5),
            ]
        );

        \App\Jobs\SendOtpMail::dispatch($request->email, $otp);

        return response()->json([
            'status' => true,
            'message' => 'OTP đã được gửi đến email',
        ]);
    }
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
        ]);
        Log::info('Sending OTP to email: ' . $request->email);
        $otp = rand(100000, 999999);
        EmailOtp::updateOrCreate(
            ['email' => $request->email],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5),
            ]
        );

        \App\Jobs\SendOtpMail::dispatch($request->email, $otp);

        return response()->json([
            'status' => true,
            'message' => 'OTP đã được gửi đến email',
        ]);
    }
    private function validationErrorResponse($validator)
    {
        $errors = $validator->errors();

        // Ưu tiên message cho email nếu có
        $msg = $errors->first('email')
            ?: $errors->first()
            ?: 'Dữ liệu không hợp lệ';

        return response()->json([
            'status' => false,
            'message' => $msg,
            'errors' => $errors,
        ], 422);
    }

}
