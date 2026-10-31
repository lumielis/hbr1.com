<?php

namespace App\Models;

use Azera\Orm\Model;

class AccessUser extends Model
{
    public int $id;
    public int $user_id;
    public int $group_id;
    public int $timestamp_start;
    public int $timestamp_update;
    public string $name;
    public string $info;
    public string $passwd;
    public string $random_key;
    public function source(): string { return 'access_user'; }
    // Properties will be added automatically by the sync task.
}