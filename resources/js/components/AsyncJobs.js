import { API } from './Api';

import { every } from 'lodash-es';

/**
 * Jobs that will be checked
 *
 * @type {Map<int, Promise>}
 */
let jobs = new Map();

/**
 * Object responsible for periodically checking for job completions, if any are requested
 *
 * @type {{stop: checker.stop, start: checker.start, intervalId: ?int, check: checker.check}}
 */
const checker = {
    /**
     * @private {?int} A Number, representing the ID value of the timer that is set. Use this value with the clearInterval() method to cancel the timer
     */
    intervalId: null,

    /**
     * Start interval responsible for checking whether the jobs are complete
     *
     * @return {void}
     */
    start: function () {
        if (!this.intervalId) {
            this.intervalId = setInterval(this.check.bind(this), 2000);
        }
    },

    /**
     * Stop interval responsible for checking whether the jobs are complete
     *
     * @return {void}
     */
    stop: function () {
        clearInterval(this.intervalId);
        delete this.intervalId;
    },

    /**
     * Execute AJAX request to check whether jobs in {@see jobs} are complete
     *
     * @return {void}
     */
    check: function () {
        let unresolvedJobIds = [];
        for (const [jobId, promise] of jobs.entries()) {
            if (!promise.resolved) {
                unresolvedJobIds.push(jobId);
            }
        }
        if (unresolvedJobIds.length === 0) {
            return;
        }
        API.checkJobs(unresolvedJobIds)
           .then(function (response) {
               for (const [jobId, status] of Object.entries(response.data)) {
                   if (status) {
                       let job = jobs.get(parseInt(jobId));
                       job?.resolve();
                       job.resolved = true;
                   }
               }
               if (every(Object.fromEntries(jobs.entries()), { resolved: true })) {
                   checker.stop();
               }
           });
    }
};

/**
 * Periodically checks for specified job's completion, when complete resolves the returned Promise
 *
 * @param {int} jobId to be periodically checked
 *
 * @return {Promise<void>}
 */
export default function whenJobComplete (jobId) {
    checker.start();
    if (!jobs.has(jobId)) {
        let resolver;
        let promise = new Promise(resolve => { resolver = resolve; });
        promise.resolve = resolver;
        promise.resolved = false;
        jobs.set(jobId, promise);
    }
    return jobs.get(jobId);
}
