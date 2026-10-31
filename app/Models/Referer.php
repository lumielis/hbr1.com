<?php

namespace App\Models;

use Azera\Orm\Model;

class Referer extends Model
{
    public string $url;
    public int $hits;
    public function source(): string { return 'referer'; }

    /** Keyed by the URL itself. */
    public function idFields(): array { return ['url']; }
}