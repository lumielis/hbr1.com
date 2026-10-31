<?php

namespace App\Models;

use Azera\Orm\Model;

class Runtime extends Model
{
    public string $Name;
    public string $Value;
    public function source(): string { return 'runtime'; }

    /** Keyed by name (`runtime.Name` is the primary key). */
    public function idFields(): array { return ['Name']; }
}