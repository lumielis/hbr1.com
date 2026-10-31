<?php

namespace App\Models;

use Azera\Orm\Model;

class ChatHistory extends Model
{
    public int $id;
    public int $timestamp;
    public string $remote_addr;
    public string $name;
    public string $content;
    public string $color;
    public function source(): string { return 'chat_history'; }
    // Properties will be added automatically by the sync task.
}