<?php

namespace App\Models;

use Azera\Orm\Model;

class Settings extends Model
{
    public int $id;
    public int $parent;
    public string $name;
    public string $variable;
    public string $value;
    public string $default_value;
    public string $type;
    public string $choice;
    public int $required;
    public function source(): string { return 'settings'; }
    // Properties will be added automatically by the sync task.
}