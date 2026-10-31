<?php

namespace App\Models;

use Azera\Orm\Model;

class ClientPerson extends Model
{
    public string $id;
    public string $pin;
    public int $category;
    public string $first_name;
    public string $last_name;
    public string $street;
    public string $state;
    public string $zip;
    public string $city;
    public string $country;
    public string $birthday;
    public string $phone;
    public string $email;
    public string $homepage;
    public int $time;
    public string $info;
    public int $charge;
    public string $fingerprint;
    public int $status;
    public function source(): string { return 'client_person'; }
    // Properties will be added automatically by the sync task.
}