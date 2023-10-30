<?php

namespace App;

use Bugsnag\BugsnagLaravel\OomBootstrapper;

/**
 * Trait KernelBootstrapperTrait
 * This trait extends HTTP and Console kernel bootstrappers() method by addding Bugsnag OomBootstrapper
 * to the bootstrap classes array. This class is responsible for increasing the memory limit on OOM errors,
 * ensuring the errors get delivered successfully.
 *
 * @package App
 */
trait KernelBootstrapperTrait {

    /**
     * Bugsnag - Increase the PHP memory limit when the app runs out of memory to ensure events can be delivered
     *
     * @return array    bootstrap classes for the application
     */
    protected function bootstrappers()
    {
        return array_merge(
            [OomBootstrapper::class],
            parent::bootstrappers(),
        );
    }
}
