<?php

namespace App\Models;

use Azera\Orm\Model;

class ReleaseTypes extends Model
{
    public int $id;
    public string $name;
    public function source(): string { return 'release_types'; }
    // Properties will be added automatically by the sync task.
}