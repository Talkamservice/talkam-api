<?php

namespace App\Models;

use App\Traits\TimezoneTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;

class AppDatabaseNotification extends DatabaseNotification
{
    use HasFactory;
}
