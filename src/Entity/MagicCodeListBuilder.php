<?php

declare(strict_types=1);

namespace Drupal\magic_code\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a list builder for Magic Code entities.
 */
class MagicCodeListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['user'] = $this->t('User');
    $header['operation'] = $this->t('Operation');
    $header['client'] = $this->t('Client');
    $header['name'] = $this->t('Code');
    $header['ttl'] = $this->t('TTL');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var MagicCodeInterface $entity */

    $expire = (int) $entity->get('expire')->getString();
    $now = \Drupal::time()->getRequestTime();
    $ttl = $expire - $now;

    if ($ttl <= 0) {
      $ttl = 0;
    }

    $row['id'] = $entity->id();
    $row['user'] = NULL;
    $row['operation'] = $entity->getOperation();
    $row['client'] = NULL;
    $row['name'] = $entity->toLink($entity->label());
    $row['ttl'] = $ttl;

    if (($user = $entity->get('auth_user_id')) && $user->entity) {
      $row['user'] = $user->entity->toLink($user->entity->label());
    }
    if (($client = $entity->get('client')) && $client->entity) {
      $row['client'] = $client->entity->toLink($client->entity->label(), 'edit-form');
    }

    return $row + parent::buildRow($entity);
  }

}
