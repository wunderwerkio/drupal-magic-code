<?php

declare(strict_types=1);

namespace Drupal\magic_code\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Interface for Magic Code entities.
 */
interface MagicCodeInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Revoke a magic code.
   *
   * @return self
   *   The entity instance.
   */
  public function revoke(): self;

  /**
   * Check if the code was revoked.
   *
   * @return bool
   *   TRUE if the magic code is revoked. FALSE otherwise.
   */
  public function isRevoked(): bool;

  /**
   * Revoke login permission for login.
   *
   * @return self
   *   The entity instance.
   */
  public function revokeLogin(): self;

  /**
   * Check if magic code permits login.
   *
   * @return bool
   *   TRUE if the login is allowed. FALSE otherwise.
   */
  public function isLoginAllowed(): bool;

  /**
   * Sets the operation for the code.
   *
   * @param string $operation
   *   The operation this code is authorizing.
   *
   * @return self
   *   The entity instance.
   */
  public function setOperation(string $operation): self;

  /**
   * Get the operation for the code.
   *
   * @return string
   *   The operation this code is authorizing.
   */
  public function getOperation(): string;

  /**
   * Sets the email address for the code.
   *
   * @param string $email
   *   The email address this code is sent to.
   *
   * @return self
   *   The entity instance.
   */
  public function setEmail(string $email): self;

  /**
   * Get the email address for the code.
   *
   * @return string
   *   The email address this code is sent to.
   */
  public function getEmail(): string;

}
