<?php

namespace App\Models;

use Azera\Orm\Model;

class GuestbookFilter extends Model
{
    public int $ID;
    public string $Field;
    public string $Match;
    public int $Case;
    public function source(): string { return 'guestbook_filter'; }
    // Properties will be added automatically by the sync task.
}