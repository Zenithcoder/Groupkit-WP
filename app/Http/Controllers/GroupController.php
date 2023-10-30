<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles the Facebook groups' management page in the web portal
 *
 * Class GroupController
 * @package App\Http\Controllers
 */
class GroupController extends Controller
{
    use GroupkitControllerBehavior;

    /**
     * Display the facebook group details
     *
     * @param int $id of the requested group
     *
     * @return Application|Factory|View|void
     */
    public function show(int $id)
    {
        return $this->currentUser->canAccessGroup($id) ? view('home') : abort(Response::HTTP_UNAUTHORIZED);
    }
}
