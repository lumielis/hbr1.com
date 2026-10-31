<?php

namespace App\Models;

use Azera\Orm\Model;

class Guestbook extends Model
{
    public int $id;
    public int $timestamp;
    public string $email;
    public string $submitter;
    public string $content;
    public int $status;
    public string $remote_addr;
    public function source(): string { return 'guestbook'; }
    // Properties will be added automatically by the sync task.
}