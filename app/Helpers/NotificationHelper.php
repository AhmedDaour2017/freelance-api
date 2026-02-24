<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Events\NotificationEvent;

class NotificationHelper
{
    public static function sendNotification($userId, $type, $message)
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message
        ]);

        event(new NotificationEvent($notification));
    }
}
