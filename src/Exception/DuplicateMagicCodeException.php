<?php

declare(strict_types=1);

namespace Drupal\magic_code\Exception;

/**
 * Exception for when a magic code already exists on creation.
 */
class DuplicateMagicCodeException extends \Exception {}
