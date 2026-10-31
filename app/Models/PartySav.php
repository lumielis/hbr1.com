<?php

namespace App\Models;

use Azera\Orm\Model;

class PartySav extends Model
{
    public int $id;
    public int $status;
    public string $title;
    public int $start_timestamp;
    public int $end_timestamp;
    public string $type;
    public string $country;
    public string $city;
    public string $djs;
    public string $live;
    public string $chillout;
    public string $deco;
    public string $location;
    public string $hotline;
    public string $organizer;
    public string $email;
    public string $url;
    public string $memo;
    public string $submitter;
    public int $create_timestamp;
    public int $edit_timestamp;
    public string $flyer;
    public string $password;
    public string $source_url;
    public string $source_name;
    public int $source_id;
    public function source(): string { return 'party_sav'; }
    // Properties will be added automatically by the sync task.
}