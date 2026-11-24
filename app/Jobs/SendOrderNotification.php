<?php

namespace App\Jobs;

use App\Mail\OrderUpdateMail;
use App\Messaging\MailConfig;
use App\Messaging\OrderNotifier;
use App\Messaging\Sms\SmsManager;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $orderId, public string $event, public string $channel) {}

    public function handle(SmsManager $sms): void
    {
        $order = Order::with('items')->find($this->orderId);
        if (! $order) {
            return;
        }

        if ($this->channel === 'sms') {
            $message = OrderNotifier::render((string) Setting::get("sms_template_{$this->event}"), $order);
            $this->attempt($order, $order->phone, $message, fn () => $sms->send($order->phone, $message));

            return;
        }

        $admin = $this->channel === 'admin_email';
        $to = $admin ? Setting::get('notify_admin_email') : $order->email;
        $mail = new OrderUpdateMail($order, $this->event, $admin);
        $this->attempt($order, (string) $to, $mail->subjectLine(), function () use ($to, $mail) {
            MailConfig::apply();
            Mail::to($to)->send($mail);
        });
    }

    /** Send, log the outcome, and let the queue retry temporary failures. */
    protected function attempt(Order $order, string $to, string $message, callable $send): void
    {
        try {
            $send();
            NotificationLog::create(['order_id' => $order->id, 'channel' => $this->channel === 'sms' ? 'sms' : 'email', 'event' => $this->event, 'recipient' => $to, 'message' => $message, 'status' => 'sent']);
        } catch (Throwable $e) {
            $lastTry = $this->job === null || $this->job->getConnectionName() === 'sync' || $this->attempts() >= $this->tries;
            if (! $lastTry) {
                throw $e; // the queue tries again later
            }
            NotificationLog::create(['order_id' => $order->id, 'channel' => $this->channel === 'sms' ? 'sms' : 'email', 'event' => $this->event, 'recipient' => $to, 'message' => $message, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }
}
