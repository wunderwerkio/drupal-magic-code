<?php

declare(strict_types=1);

namespace Drupal\magic_code;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Session\AccountInterface;

/**
 * Class responsible for collecting magic codes.
 */
class MagicCodeCollector {

  /**
   * Magic code storage.
   */
  protected EntityStorageInterface $magicCodeStorage;

  /**
   * Client / Consumer storage.
   */
  protected EntityStorageInterface $clientStorage;

  /**
   * Construct new ExpiredCollector.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $dateTime
   *   Time service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $dateTime,
  ) {
    $this->magicCodeStorage = $entityTypeManager->getStorage('magic_code');
    $this->clientStorage = $entityTypeManager->getStorage('consumer');
  }

  /**
   * Collect all expired magic code ids.
   *
   * @param int $limit
   *   Number of magic codes to fetch.
   *
   * @return \Drupal\magic_code\Entity\MagicCodeInterface[]
   *   The expired magic codes.
   */
  public function collectExpired(int $limit = 0): array {
    $query = $this->magicCodeStorage->getQuery();
    $query->accessCheck();
    $query->condition('expire', $this->dateTime->getRequestTime(), '<');

    // If limit available.
    if (!empty($limit)) {
      $query->range(0, $limit);
    }

    if (!$results = $query->execute()) {
      return [];
    }

    return array_values($this->magicCodeStorage->loadMultiple(array_values($results)));
  }

  /**
   * Collect all the magic codes associated with the provided account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param string|null $operation
   *   Optional operation to filter on.
   *
   * @return \Drupal\magic_code\Entity\MagicCodeInterface[]
   *   The magic codes.
   */
  public function collectForAccount(AccountInterface $account, ?string $operation = NULL): array {
    $query = $this->magicCodeStorage->getQuery();
    $query->accessCheck();
    $query->condition('auth_user_id', $account->id());

    // If operation was provided.
    if (!is_null($operation)) {
      $query->condition('operation', $operation);
    }

    $entityIds = $query->execute();

    $output = $entityIds
      ? array_values($this->magicCodeStorage->loadMultiple(array_values($entityIds)))
      : [];

    // Also collect the tokens of the clients that have this account as the
    // default user.
    try {
      $clients = array_values($this->clientStorage->loadByProperties([
        'user_id' => $account->id(),
      ]));
    }
    catch (QueryException $exception) {
      return $output;
    }

    // Append all the magic_code for each of the clients having this account
    // as the default.
    $tokens = array_reduce($clients, function ($carry, $client) {
      return array_merge($carry, $this->collectForClient($client));
    }, $output);

    // Return a unique list.
    $existing = [];
    foreach ($tokens as $token) {
      $existing[$token->id()] = $token;
    }

    return array_values($existing);
  }

  /**
   * Collect all the magic codes associated a particular client.
   *
   * @param \Drupal\consumers\Entity\Consumer $client
   *   The account.
   *
   * @return \Drupal\magic_code\Entity\MagicCodeInterface[]
   *   The magic codes.
   */
  public function collectForClient(Consumer $client): array {
    $query = $this->magicCodeStorage->getQuery();
    $query->accessCheck();
    $query->condition('client', $client->id());

    if (!$entityIds = $query->execute()) {
      return [];
    }

    /** @var \Drupal\magic_code\Entity\MagicCodeInterface[] $results */
    $results = $this->magicCodeStorage->loadMultiple(array_values($entityIds));

    return array_values($results);
  }

  /**
   * Deletes multiple magic codes based on ID.
   *
   * @param \Drupal\magic_code\Entity\MagicCodeInterface[] $magicCodes
   *   The magic code entity IDs.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function deleteMultipleMagicCodes(array $magicCodes = []) {
    $this->magicCodeStorage->delete($magicCodes);
  }

}
