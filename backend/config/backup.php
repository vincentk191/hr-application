<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where the application sees the server's backups
    |--------------------------------------------------------------------------
    |
    | docker-compose.server.yml mounts the host's backup folder here, read-only, in the `app` container only. It is what the
    | download button on the admin page "Cadangan data" lists and sends (README, "Download from the admin page"). Nothing
    | is there until the server is set up for it, and the page says so; the application never writes to it.
    |
    */
    'directory' => env('BACKUP_DOWNLOAD_DIR', '/backups'),

];
