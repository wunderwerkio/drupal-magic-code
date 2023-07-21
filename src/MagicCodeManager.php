<?php

declare(strict_types=1);

namespace Drupal\magic_code;

use Drupal\Component\Datetime\Time;
use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\magic_code\Entity\MagicCodeInterface;
use Drupal\magic_code\Exception\DuplicateMagicCodeException;
use Psr\Log\LoggerInterface;

/**
 * Manager service for Magic Code entities.
 */
class MagicCodeManager implements MagicCodeManagerInterface {

  const MAX_RANDOM_CODE_GENERATION_ATTEMPTS = 10;

  /**
   * Construct a new MagicCodeManager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected Time $time,
    protected FloodInterface $flood,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createNew(string $operation, AccountInterface $user, ConsumerInterface $consumer, ?string $email = NULL): MagicCodeInterface {
    $loginPermittedOperations = $this->getLoginPermittedOperations();
    $allowLogin = in_array($operation, $loginPermittedOperations);

    $expire = $this->time->getRequestTime() + $this->getCodeTtl();
    $code = $this->createUniqueCode();
    $targetEmail = $email ?? $user->getEmail();

    $payload = [
      'auth_user_id' => $user->id(),
      'value' => $code,
      'operation' => $operation,
      'expire' => $expire,
      'email' => $targetEmail,
      'status' => TRUE,
      'login_allowed' => $allowLogin,
      'client' => [
        'target_id' => $consumer->id(),
      ],
    ];

    $magicCode = $this->entityTypeManager->getStorage('magic_code')->create($payload);
    $magicCode->save();

    return $magicCode;
  }

  /**
   * {@inheritdoc}
   */
  public function verify(
    string $codeValue,
    string $operation,
    int $mode,
    AccountInterface $user,
    ConsumerInterface $consumer,
    ?string $email = NULL,
  ): MagicCodeResult {
    $floodConfig = $this->getFloodConfig();
    $targetEmail = $email ?? $user->getEmail();
    $identifier = $user->id();

    // Flood protection: This is needed to prevent brute force attacks via
    // magic codes. The implementation is very similar to the basic_auth module.
    // @see \Drupal\basic_auth\Authentication\Provider\BasicAuth::authenticate()
    // Do not allow magic code verification from the current user's IP if
    // the limit has been reached. Default is 50 failed verifications in one
    // hour. This is independent of the per-user limit to catch attempts from
    // one IP to verify many different magic codes. We have a reasonably high
    // limit since there may be only one apparent IP for all users at an
    // institution.
    // Important: Always check IP limit before the user limit and never register
    // a failed user attempt if the IP is blocked! That would otherwise enable
    // an attacker to do a DOS (but not DDOS) attack for the given user,
    // even if the attacker's IP is already blocked.
    //
    // First, we check the IP limit.
    // If this limit is reached, we abort early and register a
    // IP failed attempt.
    if (!$this->flood->isAllowed('magic_code.failed_verification_ip', $floodConfig['ip_limit'], $floodConfig['ip_window'])) {
      // Always register IP verification failure.
      $this->flood->register('magic_code.failed_verification_ip', $floodConfig['ip_window']);

      $this->logger->warning('Magic code verification failed for user @user. IP blocked.', ['@user' => $user->id()]);

      return MagicCodeResult::BlockedByIp;
    }

    // Now, we check the user limit.
    // If this limit is reached, we register an IP and User failed attempt.
    if (!$this->flood->isAllowed('magic_code.failed_verification_user', $floodConfig['user_limit'], $floodConfig['user_window'], $identifier)) {
      // Always register IP verification failure.
      $this->flood->register('magic_code.failed_verification_ip', $floodConfig['ip_window']);

      $this->logger->warning('Magic code verification failed for user @user. User blocked.', ['@user' => $user->id()]);

      return MagicCodeResult::BlockedByUser;
    }

    // User and IP are not blocked, continue with verification.
    $query = $this->entityTypeManager->getStorage('magic_code')->getQuery();

    $query = $query
      ->accessCheck(FALSE)
      ->condition('value', $codeValue)
      ->condition('auth_user_id', $user->id())
      ->condition('email', $targetEmail)
      ->condition('operation', $operation)
      ->condition('client', $consumer->id())
      ->condition('status', TRUE)
      ->condition('expire', $this->time->getRequestTime(), '>=');

    // If verifying login, the login must be allowed.
    if ($mode === self::VERIFY_MODE_LOGIN) {
      $query->condition('login_allowed', TRUE);
    }

    $ids = $query
      ->execute();

    // No match found.
    if (empty($ids)) {
      // Register verification failure.
      $this->flood->register('magic_code.failed_verification_ip', $floodConfig['ip_window']);
      $this->flood->register('magic_code.failed_verification_user', $floodConfig['user_window'], $identifier);

      $this->logger->warning('Magic code @code is not found for user @user.', [
        '@code' => $codeValue,
        '@user' => $user->id(),
      ]);

      return MagicCodeResult::Invalid;
    }

    $id = reset($ids);

    // Load code entity.
    $entity = $this->entityTypeManager->getStorage('magic_code')->load($id);
    /** @var \Drupal\magic_code\Entity\MagicCodeInterface $entity */

    // Revoke login if mode is login.
    if ($mode === self::VERIFY_MODE_LOGIN) {
      $entity->revokeLogin()->save();
    }

    // Revoke code itself for all other modes, except
    // if the operation is login.
    if ($mode !== self::VERIFY_MODE_LOGIN || $operation === 'login') {
      $entity->revoke()->save();
    }

    // Clear user verification limit.
    $this->flood->clear('magic_code.failed_verification_user', $identifier);

    return MagicCodeResult::Success;
  }

  /**
   * {@inheritdoc}
   */
  public function revoke(string $id) {
    /** @var \Drupal\magic_code\Entity\MagicCodeInterface $code */
    $code = $this->entityTypeManager->getStorage('magic_code')->load($id);

    if ($code) {
      $code->revoke()->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function revokeMultiple(array $ids) {
    foreach ($ids as $id) {
      $this->revoke($id);
    }
  }

  /**
   * Tries to generate a unique magic code value.
   *
   * Gives up after MAX_RANDOM_CODE_GENERATION_ATTEMPTS attempts.
   *
   * @return string
   *   The generated magic code.
   */
  protected function createUniqueCode(): string {
    $maxGenerationAttempts = self::MAX_RANDOM_CODE_GENERATION_ATTEMPTS;
    while ($maxGenerationAttempts-- > 0) {
      $code = $this->generateCode();

      // Query for existing entity with same code.
      $existingEntityCount = $this->entityTypeManager
        ->getStorage('magic_code')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('value', $code)
        ->count()
        ->execute();

      // Code is unique.
      if ($existingEntityCount === 0) {
        return $code;
      }
    }

    throw new DuplicateMagicCodeException('Magic code value must be unique.');
  }

  /**
   * Generates the magic code value.
   *
   * This results in a six digit code with
   * a dash in the middle.
   *
   * E.g.: 2CV-UGB.
   *
   * @return string
   *   The generated magic code.
   */
  protected function generateCode(): string {
    // Pool of alphanumeric chars, excluding 0 and O to
    // avoid user confusion.
    $chars = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ';

    $buf = '';

    // Generate code.
    for ($i = 0; $i < 6; ++$i) {
      $buf .= $chars[random_int(0, strlen($chars) - 1)];

      // Add dash.
      if ($i === 2) {
        $buf .= '-';
      }
    }

    return $buf;
  }

  /**
   * Get the magic code ttl from the config.
   *
   * @return int
   *   The magic code ttl.
   */
  protected function getCodeTtl() {
    return (int) $this->configFactory->get('magic_code.settings')->get('code_ttl');
  }

  /**
   * Get the magic code flood config.
   *
   * @return array
   *   The magic code flood config.
   */
  protected function getFloodConfig() {
    return $this->configFactory->get('magic_code.settings')->get('flood');
  }

  /**
   * Gets login permitted operations from config.
   *
   * @return array
   *   An array of operations that permit login.
   */
  protected function getLoginPermittedOperations() {
    return $this->configFactory->get('magic_code.settings')->get('login_permitted_operations');
  }

}
