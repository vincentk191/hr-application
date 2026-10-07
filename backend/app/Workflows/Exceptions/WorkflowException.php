<?php

namespace App\Workflows\Exceptions;

use RuntimeException;

/**
 * Base for every refusal the engine makes. Catch this to turn a workflow refusal into a
 * validation error rather than a 500.
 */
abstract class WorkflowException extends RuntimeException {}
