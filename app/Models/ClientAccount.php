<?php

namespace App\Models;

use Azera\Orm\Model;

class ClientAccount extends Model
{
    public int $id;
    public string $client;
    public int $time;
    public string $info;
    public string $sender;
    public int $amount;
    public int $charge;
    public function source(): string { return 'client_account'; }
    // Properties will be added automatically by the sync task.
}