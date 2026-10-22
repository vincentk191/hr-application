<?php

/*
|--------------------------------------------------------------------------
| Uploaded files
|--------------------------------------------------------------------------
|
| PDFs filed as leave attachments, employee documents and offboarding files are shrunk with Ghostscript before
| they are stored. It never gets in the way: a PDF Ghostscript cannot read, or one that does not come out smaller,
| is stored exactly as it was uploaded, and so is every PDF when Ghostscript is not installed.
|
| The per-file size limits are not here: each upload's validation rules own its own (leave attachments 5 MB,
| documents and offboarding files 10 MB), and the file pickers in the frontend repeat them.
|
*/

return [

    'pdf' => [

        'compress' => env('UPLOADS_COMPRESS_PDF', true),

        // The Ghostscript executable.
        'ghostscript' => env('UPLOADS_GHOSTSCRIPT', 'gs'),

        // Ghostscript's -dPDFSETTINGS preset. /ebook keeps scans and photos readable on a screen (150 dpi
        // images); /screen is smaller and visibly softer, /printer is larger and sharper.
        'preset' => env('UPLOADS_PDF_PRESET', '/ebook'),

        // A PDF under this size is stored as is: there is little to win and a rewrite is not free.
        'minimum_kb' => 200,

        // Seconds Ghostscript may spend on one file before it is given up on and the original is kept.
        'timeout' => 30,

    ],

];
