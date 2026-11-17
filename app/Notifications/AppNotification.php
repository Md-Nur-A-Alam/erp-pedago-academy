<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Every in-app notice. It is kept in the database and shown in the bell and the Notifications page.
 */
class AppNotification extends Notification
{
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $tone = 'blue',
        public ?string $key = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string|null>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tone' => $this->tone,
            'key' => $this->key,
        ];
    }
}
