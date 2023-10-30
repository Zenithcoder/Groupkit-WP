<?php

return [
    /*
     * This is used in our stripe:sync-customers command to maintain the limit of data we receive in one call
     */
    'sync_customers' => [
        'chunk_size' => 100,
    ],
];
