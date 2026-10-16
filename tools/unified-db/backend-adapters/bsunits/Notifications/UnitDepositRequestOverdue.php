<?php

namespace App\Notifications;

use App\UnitDepositRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class UnitDepositRequestOverdue extends Notification
{
    use Queueable;

    /**
     * The UnitDepositRequest instance.
     *
     * @var unit_deposit_request
     */
    public $unit_deposit_request;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(UnitDepositRequest $unit_deposit_request)
    {
        $this->unit_deposit_request = $unit_deposit_request;  
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        if (config('unified_catalog.overdue_local_database_notifications', false) && app()->environment('local')) return ['database'];
        return ['database', OneSignalChannel::class];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {   
        return [
            'id' => $this->unit_deposit_request->id,
            'action' => 'Overdue',
            'type' => 'unit_deposit_request',
            'additional_data' => [
                'unit_code' => $this->unitCode(),
            ]
        ];
    }

    /**
     * Send OneSignal notification
     *
     *
     */
    public function toOneSignal($notifiable)
    {       
        return OneSignalMessage::create()
            ->subject("មានសម្នើរកក់ប្រាក់")
            ->body("អ្នកមិនទាន់ស្នើរកុងត្រា បន្ទាប់ពីកក់ប្រាក់លើអចលនទ្រព្យ ". $this->unitCode()." ជាង១សប្តាហ័ហើយ។")
            ->icon('ic_logo_gray')
            ->setData('notification_id', $this->id)
            ->setData('id', $this->unit_deposit_request->id)
            ->setData('type', 'unit_deposit_request_overdue');
    }
    private function unitCode()
    {
        if (!config('unified_catalog.request_writes_enabled')) return $this->unit_deposit_request->unit->code;
        $table = config('unified_catalog.request_unit_table', 'units');
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) throw new \LogicException('Invalid canonical unit table.');
        return $this->unit_deposit_request->getConnection()->table($table)->where('id', $this->unit_deposit_request->unit_id)->value('code');
    }

}
