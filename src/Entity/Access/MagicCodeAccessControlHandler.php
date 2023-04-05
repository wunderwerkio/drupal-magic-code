<?php

declare(strict_types=1);

namespace Drupal\magic_code\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Magic Code entity.
 *
 * @see \Drupal\magic_code\Entity\MagicCode.
 */
class MagicCodeAccessControlHandler extends EntityAccessControlHandler {

  /**
   * The entity id.
   */
  public static string $name = 'magic_code';

  /**
   * Check Magic Code entity access.
   *
   * Allow if admin permission.
   * Allow for entity owner if owner has the
   * `{operation} own magic_code entities` permission.
   *
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var MagicCodeInterface $entity */

    // Check admin permission.
    $adminPermission = $this->entityType->getAdminPermission();
    if ($account->hasPermission($adminPermission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Permissions only apply to own entities.
    $isOwner = ($account->id() && $account->id() === $entity->getOwnerId());
    $isOwnerAccess = AccessResult::allowedIf($isOwner)
      ->addCacheableDependency($entity);

    $operations = ['view', 'update', 'delete'];
    if (!in_array($operation, $operations)) {
      $reason = sprintf(
        'Supported operations on the entity are %s',
        implode(', ', $operations)
      );
      return AccessResult::neutral($reason);
    }

    return $isOwnerAccess->andIf(AccessResult::allowedIfHasPermission(
      $account,
      sprintf('%s own %s entities', $operation, static::$name)
    )->cachePerPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, sprintf('add %s entities', static::$name));
  }

}
