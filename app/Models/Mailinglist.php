<?php

namespace App\Models;

use Azera\Orm\Model;

class Mailinglist extends Model
{
    public int $id;
    public string $name;
    public string $email;
    public string $timestamp_create;
    public string $timestamp_modify;
    public string $verify;
    public int $status;
    public int $user_id;
    public int $group_id;
    public int $access;
    public function source(): string { return 'mailinglist'; }
    // Properties will be added automatically by the sync task.
}