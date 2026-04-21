<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\ForgotPassword as ForgotPasswordMailable;
use Illuminate\Support\Facades\Log;

class SendOtpMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $email;
    protected $otp;
    public function __construct($email, $otp)
    {
        $this->email = $email;
        $this->otp = $otp;
    }
    public function handle(): void
    {

        try {
            \Log::info('Gửi mail OTP tới: ' . $this->email . ' với OTP: ' . $this->otp);
            Mail::to($this->email)->send(new ForgotPasswordMailable($this->otp));
        } catch (\Throwable $e) {
            Log::error('Lỗi gửi mail OTP: ' . $e->getMessage());
        }
    }
}
