<?php

namespace App\Models;

use Azera\Orm\Model;

class Releases extends Model
{
    public int $id;
    public int $mix;
    public int $type;
    public string $name;
    public int $label;
    public ?string $url_order;
    public int $pos;
    public int $hits;
    public function source(): string { return 'releases'; }
    // Properties will be added automatically by the sync task.
}