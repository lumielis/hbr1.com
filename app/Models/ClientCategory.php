<?php

namespace App\Models;

use Azera\Orm\Model;

class ClientCategory extends Model
{
    public int $id;
    public string $name;
    public int $fixed;
    public int $status;
    public function source(): string { return 'client_category'; }
    // Properties will be added automatically by the sync task.
}