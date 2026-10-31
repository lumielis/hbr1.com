<?php

namespace App\Models;

use Azera\Orm\Model;

class UserInfo extends Model
{
    public int $id;
    public int $user_id;
    public string $title;
    public string $name;
    public string $surname;
    public string $street;
    public string $state;
    public string $zip;
    public string $city;
    public string $country;
    public string $birthday;
    public string $phone;
    public string $handy;
    public string $email;
    public function source(): string { return 'user_info'; }
    // Properties will be added automatically by the sync task.
}