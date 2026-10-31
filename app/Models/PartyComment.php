<?php

namespace App\Models;

use Azera\Orm\Model;

class PartyComment extends Model
{
    public int $id;
    public int $party_id;
    public int $create_timestamp;
    public string $submitter;
    public string $email;
    public string $info;
    public function source(): string { return 'party_comment'; }
    // Properties will be added automatically by the sync task.
}