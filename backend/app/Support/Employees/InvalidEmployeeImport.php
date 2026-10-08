<?php

namespace App\Support\Employees;

use RuntimeException;

/**
 * The uploaded file cannot be read as an employee list at all (as opposed to containing some bad
 * rows, which are counted and reported). The message is written for the person who uploaded it.
 */
class InvalidEmployeeImport extends RuntimeException {}
