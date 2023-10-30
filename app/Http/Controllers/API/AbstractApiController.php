<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use Illuminate\Routing\Controller;

/**
 * The base class for all API controllers
 *
 * @package App\Http\Controllers\API
 */
abstract class AbstractApiController extends Controller
{
    use GroupkitControllerBehavior;

    /**
     * @var string The authentication guard used for user's of this controller
     */
    protected $guardType = 'api';
}
