<?php

namespace App\Support\Google;

use RuntimeException;

/**
 * The calendar event no longer exists on Google: someone deleted it there.
 */
class GoogleEventGone extends RuntimeException {}
