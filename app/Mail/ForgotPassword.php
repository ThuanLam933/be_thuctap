<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPassword extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public $otp)
    {
        //
    }
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Mã xác thực OTP',
        );
    }
    public function content(): Content
    {
        return new Content(
            view: 'mail.forgotPassword',
            with: [
                'otp' => $this->otp,
            ],
        );
    }
    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
