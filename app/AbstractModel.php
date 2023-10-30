<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The base class for all Models that need custom eloquent methods
 *
 * @package App
 */
abstract class AbstractModel extends Model
{
    /**
     * Filters @see \App\Model with provided $request
     *
     * @param Request|array $request which contains or not parameters for filtering
     *
     * @return Builder with filtered parameters
     */
    abstract public static function filterBy($request): Builder;
}
