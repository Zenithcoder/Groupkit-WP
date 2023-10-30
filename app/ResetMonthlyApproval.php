<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResetMonthlyApproval extends Model
{
    use HasFactory;

    /**
     * @var string[] Allowed values to be saved via the web interface/API
     */
    protected $fillable = [
        'user_id',
    ];
}
