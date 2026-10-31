<?php

namespace App\Models;

use Azera\Orm\Model;

class AccessModule extends Model
{
    public int $id;
    public string $module;
    public int $user_id;
    public int $group_id;
    public int $access;
    public function source(): string { return 'access_module'; }
    // Properties will be added automatically by the sync task.
}