<?php

namespace App\Models;

use Azera\Orm\Model;

class Mixes extends Model
{
    public int $id;
    public int $category;
    public string $dj;
    public string $name;
    public int $timestamp;
    public int $length;
    public int $status;
    public function source(): string { return 'mixes'; }
    // Properties will be added automatically by the sync task.
}