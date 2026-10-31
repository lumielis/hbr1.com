<?php

namespace App\Models;

use Azera\Orm\Model;

class Country extends Model
{
    public string $id;
    public string $name;
    public function source(): string { return 'country'; }
    // Properties will be added automatically by the sync task.
}