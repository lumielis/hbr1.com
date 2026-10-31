<?php

namespace App\Models;

use Azera\Orm\Model;

class MessagePicture extends Model
{
    public int $id;
    public int $message_id;
    public string $title;
    public string $file;
    public string $copyright;
    public function source(): string { return 'message_picture'; }
    // Properties will be added automatically by the sync task.
}