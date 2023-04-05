<?php

declare(strict_types=1);

namespace Drupal\magic_code;

use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\magic_code\Entity\MagicCodeInterface;

/**
 * Interface for the Magic Code Manager.
 */
interface MagicCodeManagerInterface {

  const VERIFY_MODE_LOGIN = 1;
  const VERIFY_MODE_OPERATION = 2;

  /**
   * Create a new magic code.
   *
   * Throws an error if the generated code already exists.
   * When using this method, make sure to retry in case
   * creation fails.
   *
   * @param string $operation
   *   The operation this code authorizes.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to create the magic code for.
   * @param \Drupal\consumers\Entity\ConsumerInterface $consumer
   *   The consumer this code is for.
   * @param string|null $email
   *   The email address this code is sent to. If NULL the
   *   email is set to the user's email address.
   *
   * @return \Drupal\magic_code\Entity\MagicCodeInterface
   *   The created magic code.
   *
   * @throws \Drupal\magic_code\Exception\DuplicateMagicCodeException
   *   Thrown if the code already exists.
   */
  public function createNew(
    string $operation,
    AccountInterface $user,
    ConsumerInterface $consumer,
    ?string $email = NULL,
  ): MagicCodeInterface;

  /**
   * Verifies a magic code.
   *
   * Checks if the given magic code exists, factoring in
   * multiple factors, like user id, email, operation and client.
   *
   * If a match was found, revokes the magic code.
   *
   * @param string $codeValue
   *   The magic code value.
   * @param string $operation
   *   The operation this code authorizes.
   * @param int $mode
   *   The verification mode. Either self::VERIFY_MODE_LOGIN or
   *   self::VERIFY_MODE_OPERATION.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to create the magic code for.
   * @param \Drupal\consumers\Entity\ConsumerInterface $consumer
   *   The consumer this code is for.
   * @param string|null $email
   *   The email address this code is sent to. If NULL the
   *   email is set to the user's email address.
   *
   * @return MagicCodeResult
   *   The result of the verification.
   */
  public function verify(
    string $codeValue,
    string $operation,
    int $mode,
    AccountInterface $user,
    ConsumerInterface $consumer,
    ?string $email = NULL,
  ): MagicCodeResult;

  /**
   * Revokes a magic code by id.
   *
   * @param string $id
   *   The id of the Magic Code entity.
   */
  public function revoke(string $id);

  /**
   * Revokes multiple magic codes.
   *
   * @param string[] $ids
   *   Array of Magic Code entity ids.
   */
  public function revokeMultiple(array $ids);

}
