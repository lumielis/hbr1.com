<?php

namespace App\Models;

use Azera\Orm\Model;

class MessageThread extends Model
{
    public int $id;
    public int $category_id;
    public string $submitter;
    public string $password;
    public string $title;
    public string $memo;
    public int $status;
    public int $create_timestamp;
    public int $edit_timestamp;
    public function source(): string { return 'message_thread'; }
    // Properties will be added automatically by the sync task.
}