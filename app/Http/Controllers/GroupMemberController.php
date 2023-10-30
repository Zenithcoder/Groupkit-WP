<?php

namespace App\Http\Controllers;

use App\GroupMembers;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Routing\Controller;

/**
 * Handles the group members action for web routes
 *
 * Class GroupMemberController
 * @package App\Http\Controllers
 */
class GroupMemberController extends Controller
{
    /**
     * Downloads csv file from the storage if exists, otherwise returns not found view
     *
     * @param string $fileName of the csv that will be downloaded
     *
     * @return BinaryFileResponse containing csv file with group member data
     */
    public function downloadCSV(string $fileName): BinaryFileResponse
    {
        if (!Storage::disk('local')->exists(GroupMembers::CSV_FILES_PATH . $fileName)) {
            abort(Response::HTTP_NOT_FOUND, __('File not found'));
        }

        return response()
            ->download(config('filesystems.disks.local.root') . '/' . GroupMembers::CSV_FILES_PATH . $fileName);
    }
}
