<?php

namespace App\Models;

use Azera\Orm\Model;

class Access extends Model
{
    public int $id;
    public int $user_id;
    public int $group_id;
    public int $timestamp_start;
    public int $timestamp_update;
    public string $session_key;
    public int $duration;
    public int $hits;
    public function source(): string { return 'access'; }
    // Properties will be added automatically by the sync task.
}