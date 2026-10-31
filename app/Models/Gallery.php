<?php

namespace App\Models;

use Azera\Orm\Model;

class Gallery extends Model
{
    public int $id;
    public int $category_id;
    public string $submitter;
    public string $password;
    public string $title;
    public string $info;
    public int $status;
    public int $create_timestamp;
    public int $edit_timestamp;
    public function source(): string { return 'gallery'; }
    // Properties will be added automatically by the sync task.
}