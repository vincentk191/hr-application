<?php

namespace App\Support\Attendance;

use RuntimeException;

/**
 * The uploaded file cannot be read as a scan log at all (as opposed to containing some bad rows,
 * which are counted and reported). The message is written for the person who uploaded it.
 */
class InvalidScanLog extends RuntimeException {}
