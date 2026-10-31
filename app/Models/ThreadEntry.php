<?php

namespace App\Models;

use Azera\Orm\Model;

class ThreadEntry extends Model
{
    public int $id;
    public int $parent;
    public int $category;
    public int $client;
    public int $time;
    public string $subject;
    public string $message;
    public int $status;
    public function source(): string { return 'thread_entry'; }
    // Properties will be added automatically by the sync task.
}