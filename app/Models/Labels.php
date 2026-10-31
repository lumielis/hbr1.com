<?php

namespace App\Models;

use Azera\Orm\Model;

class Labels extends Model
{
    public int $id;
    public string $name;
    public ?string $home;
    public ?string $email;
    public ?int $hits;
    public function source(): string { return 'labels'; }
    // Properties will be added automatically by the sync task.
}