<?php

namespace App\Models;

use Azera\Orm\Model;

class TzZones extends Model
{
    public int $id;
    public string $name;
    public int $gmtoff;
    public string $rules;
    public string $format;
    public string $until;
    public int $is_def;
    public function source(): string { return 'tz_zones'; }
    // Properties will be added automatically by the sync task.
}