<?php

declare(strict_types=1);

namespace Drupal\magic_code\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Magic Code entity.
 *
 * @ingroup magic_code
 *
 * @ContentEntityType(
 *   id = "magic_code",
 *   label = @Translation("Magic Code"),
 *   handlers = {
 *     "storage_schema" = "Drupal\magic_code\Entity\MagicCodeStorageSchema",
 *     "list_builder" = "Drupal\magic_code\Entity\MagicCodeListBuilder",
 *     "form" = {
 *       "delete" = "Drupal\magic_code\Entity\Form\MagicCodeDeleteForm",
 *     },
 *     "access" = "Drupal\magic_code\Entity\Access\MagicCodeAccessControlHandler",
 *   },
 *   base_table = "magic_code",
 *   admin_permission = "administer magic_code entities",
 *   field_indexes = {
 *     "value"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "value",
 *     "uuid" = "uuid",
 *     "owner" = "auth_user_id",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/magic_code/magic_code/{magic_code}",
 *     "delete-form" = "/admin/content/magic_code/magic_code/{magic_code}/delete"
 *   },
 *   list_cache_tags = { "magic_code" },
 * )
 */
class MagicCode extends ContentEntityBase implements MagicCodeInterface {

  use EntityChangedTrait, EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType) {
    $fields = parent::baseFieldDefinitions($entityType);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setDescription(new TranslatableMarkup('The ID of the Magic Code entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(new TranslatableMarkup('UUID'))
      ->setDescription(new TranslatableMarkup('The UUID of the Magic Code entity.'))
      ->setReadOnly(TRUE);

    $fields['auth_user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('User'))
      ->setDescription(new TranslatableMarkup('The user ID of the user this magic code is authorizing.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'author',
        'weight' => 1,
      ])
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ]);

    $fields['client'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Client'))
      ->setDescription(new TranslatableMarkup('The consumer client for this Magic Code.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'consumer')
      ->setSetting('handler', 'default')
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 2,
      ]);

    $fields['value'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Code'))
      ->setDescription(new TranslatableMarkup('The magic code value.'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 4,
      ]);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('E-Mail'))
      ->setDescription(new TranslatableMarkup('The email address this magic code was generated for.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'email',
        'weight' => 4,
      ]);

    $fields['operation'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Operation'))
      ->setDescription(new TranslatableMarkup('The operation this code is authorizing.'))
      ->setSettings([
        'max_length' => 256,
        'text_processing' => 0,
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'text',
        'weight' => 4,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDescription(new TranslatableMarkup('The time that the entity was created.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 5,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDescription(new TranslatableMarkup('The time that the entity was last edited.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 6,
      ]);

    $fields['expire'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Expire'))
      ->setDescription(new TranslatableMarkup('The time when the magic code expires.'))
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 7,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 7,
      ])
      ->setRequired(TRUE);

    $fields['login_allowed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Login status'))
      ->setDescription(new TranslatableMarkup('A boolean indicating whether this magic code was used to log in the user.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 8,
      ])
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(FALSE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Publishing status'))
      ->setDescription(new TranslatableMarkup('A boolean indicating whether the magic code is available.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 8,
      ])
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function revoke(): self {
    $this->set('status', FALSE);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isRevoked(): bool {
    return !$this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function revokeLogin(): self {
    $this->set('login_allowed', FALSE);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isLoginAllowed(): bool {
    return $this->get('login_allowed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail(string $email): self {
    $this->set('email', $email);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): string {
    return $this->get('email')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setOperation(string $operation): self {
    $this->set('operation', $operation);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperation(): string {
    return $this->get('operation')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    // Credits go to the awesome simple_oauth module!
    // Magic codes are only used for authorization. Hence it does not
    // make sense for a magic code to be a cacheable dependency. Consequently
    // generating a unique cache tag for every magic code entity should be
    // avoided. Therefore a single cache tag is used for all magic code
    // entities, including for lists.
    return ['magic_code'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Same reasoning as in ::getCacheTagsToInvalidate().
    return static::getCacheTagsToInvalidate();
  }

}
