<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

/**
 * Email xác nhận địa chỉ khi đăng ký (FR-AUTH mở rộng cho production).
 * Link là signed URL 60 phút tới route `verification.verify`.
 */
class VerifyEmailNotification extends BaseVerifyEmail
{
    /**
     * Build the URL verification notification.
     *
     * @param  mixed  $notifiable
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return parent::toMail($notifiable)
            ->subject('Xác nhận địa chỉ email — TrustGate')
            ->greeting('Xin chào '.$notifiable->name.',')
            ->line('Bạn vừa đăng ký tài khoản TrustGate. Bấm nút bên dưới để xác nhận địa chỉ email.')
            ->line('Liên kết có hiệu lực trong 60 phút.')
            ->action('Xác nhận email', $url)
            ->line('Nếu bạn không thực hiện đăng ký này, bỏ qua email này — tài khoản sẽ không được kích hoạt.')
            ->salutation(new HtmlString('Trân trọng,<br>TrustGate'));
    }
}
