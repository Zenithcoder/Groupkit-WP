<?php

namespace App\Http\Controllers\API;

use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Bugsnag\Report;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API end-point for determine scheduled jobs status
 *
 * @package App\Http\Controllers\API
 */
class JobController extends AbstractApiController
{
    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'check' => [
            'job_ids' => 'array',
        ],
    ];

    /**
     * Checks whether any of the provided job ids are complete, and returns JSON object where keys are job IDs
     * and values are their completion statuses
     * {
     *     "12": true,
     *     "13": false
     * }
     *
     * @return HttpResponse containing status information for provided job ids
     */
    public function check(): HttpResponse
    {
        $jobIds = $this->request->job_ids;
        if (empty($jobIds)) {
            return response();
        }

        switch (config('queue.default')) {
            case 'sync':
                //shouldn't happen in practice, but if it happens we report all jobs as done
                return response(array_combine($jobIds, array_fill(0, count($jobIds), true)));
            case 'database':
                $incompleteJobIds = DB::table(config('queue.connections.database.table'))
                    ->whereIn('id', $jobIds)
                    ->pluck('id')
                    ->toArray();

                return response(
                    array_map(
                        function ($jobId) use ($incompleteJobIds) {
                            return !in_array($jobId, $incompleteJobIds);
                        },
                        array_combine($jobIds, $jobIds)
                    )
                );
            default:
                Bugsnag::notifyError(
                    'Unsupported configuration',
                    'Queue driver is not supported',
                    function (Report $report) {
                        $report->addMetaData(
                            [
                                'config' => [
                                    'queue' => config('queue'),
                                ],
                            ]
                        );
                    }
                );

                return response(
                    ['message' => __('Queue driver is not supported')],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
        }
    }
}
