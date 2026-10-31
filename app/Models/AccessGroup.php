<?php

namespace App\Models;

use Azera\Orm\Model;

class AccessGroup extends Model
{
    public int $id;
    public int $group_id;
    public string $name;
    public string $info;
    public function source(): string { return 'access_group'; }
    // Properties will be added automatically by the sync task.
}