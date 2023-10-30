<?php

namespace App\Traits;

use App\Plan;

/**
 * This trait extends functionality for the groups,
 * group members and group owners. It contains the reusable
 * parts that can be implemented in the required places in
 * the app.
 */
trait GroupTrait
{
    /**
     * Returns link that is shown in the modal to the team owner
     * when a group(s) reaches some of the limits related to groups.
     *
     * @return string
     */
    public function upgradePlanLink(): string
    {
        return sprintf(
            '<a href="%s" target="_blank" rel="noopener">%s</a>',
            route('setting'),
            __(Plan::UPGRADE_PLAN_TEXT)
        );
    }
}
