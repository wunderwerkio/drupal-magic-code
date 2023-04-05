<?php

/**
 * @file
 * MagicCodeResult Enum. Remove this once drupal/coder finally supports enums.
 */

declare(strict_types=1);

namespace Drupal\magic_code;

/**
 * Represents magic code verification result.
 */
enum MagicCodeResult: string {

  case Success = 'success';
  case BlockedByIp = 'blocked_by_ip';
  case BlockedByUser = 'blocked_by_user';
  case Invalid = 'invalid';

}
