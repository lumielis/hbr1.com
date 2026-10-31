<?php

namespace App\Models;

use Azera\Orm\Model;

class Ticker extends Model
{
    public int $id;
    public string $title;
    public string $content;
    public string $image;
    public int $show_image;
    public string $bg_color;
    public int $delay;
    public string $title_font_family;
    public string $title_font_size;
    public string $title_color;
    public int $show_title;
    public string $content_font_family;
    public string $content_font_size;
    public string $content_color;
    public string $anchor_color;
    public int $create_timestamp;
    public int $edit_timestamp;
    public int $status;
    public int $position;
    public int $border_horizontal;
    public int $border_vertical;
    public string $object_type;
    public string $flash_movie;
    public function source(): string { return 'ticker'; }
    // Properties will be added automatically by the sync task.
}