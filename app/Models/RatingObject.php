<?php

namespace App\Models;

use Azera\Orm\Model;

class RatingObject extends Model
{
    public int $id;
    public int $category;
    public int $object;
    public int $choice_1;
    public int $choice_2;
    public int $choice_3;
    public int $choice_4;
    public int $choice_5;
    public int $choice_6;
    public int $result;
    public int $status;
    public function source(): string { return 'rating_object'; }
    // Properties will be added automatically by the sync task.
}