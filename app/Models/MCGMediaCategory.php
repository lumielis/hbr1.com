<?php

namespace App\Models;

use Azera\Orm\Model;

class MCGMediaCategory extends Model
{
    public int $MCG_Index;
    public string $MCG_Name;
    public string $MCG_Path;
    public int $MCG_Fixed;
    public int $MCG_Status;
    public int $MCG_Flags;
    public function source(): string { return 'MCG_MediaCategory'; }

    /** The legacy gallery tables use their own index column, not `id`. */
    public function idFields(): array { return ['MCG_Index']; }
}