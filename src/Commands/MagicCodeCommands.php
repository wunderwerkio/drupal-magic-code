<?php

declare(strict_types=1);

namespace Drupal\magic_code\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\magic_code\MagicCodeManagerInterface;
use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the magic code module.
 */
class MagicCodeCommands extends DrushCommands {

  /**
   * Construct the MagicCodeCommands object.
   *
   * @param \Drupal\magic_code\MagicCodeManagerInterface $magicCodeManager
   *   The magic code manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected MagicCodeManagerInterface $magicCodeManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Generate a magic code.
   *
   * @command magic-code:generate
   * @aliases mcg
   * @option uid
   *   The user ID to generate the magic code for.
   */
  public function generateMagicCode(string $operation, array $options = [
    'uid' => NULL,
    'client-id' => NULL,
    'email' => NULL,
  ]) {
    $clientStorage = $this->entityTypeManager->getStorage('consumer');

    // Get required data.
    $uid = $options['uid'];
    $clientId = $options['client-id'];
    $email = $options['email'];

    if (!$uid) {
      $this->output()->writeln('<error>No user ID specified.</error>');
      return;
    }

    // Load user.
    $user = User::load($uid);
    if (!$user) {
      $this->output()->writeln('<error>No user found with ID: ' . $uid . '.</error>');
      return;
    }

    // Load consumer.
    $props = [];
    if ($clientId) {
      $props['client_id'] = $clientId;
    }
    else {
      $props['is_default'] = TRUE;
    }

    $entities = $clientStorage->loadByProperties($props);
    if (empty($entities)) {
      $this->output()->writeln('<error>No client found with ID: ' . $clientId . '.</error>');
      return;
    }
    $client = reset($entities);

    // Generate the code.
    $code = $this->magicCodeManager->createNew($operation, $user, $client, $email);

    // Output the code.
    $this->output()->writeln('<info>' . $code->label() . '</info>');
  }

}
