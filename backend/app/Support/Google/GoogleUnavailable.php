<?php

namespace App\Support\Google;

use RuntimeException;

/**
 * Google could not be reached, or answered with something the app cannot use (the Calendar API switched off for the
 * project, a quota, a server error). The message is for the log; HR is told in general words.
 */
class GoogleUnavailable extends RuntimeException {}
