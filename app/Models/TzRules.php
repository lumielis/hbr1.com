<?php

namespace App\Models;

use Azera\Orm\Model;

class TzRules extends Model
{
    public int $id;
    public string $name;
    public string $from;
    public string $to;
    public string $type;
    public string $in;
    public string $on;
    public string $at;
    public int $save;
    public string $letter;
    public function source(): string { return 'tz_rules'; }
    // Properties will be added automatically by the sync task.
}