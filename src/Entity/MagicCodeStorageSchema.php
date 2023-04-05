<?php

namespace Drupal\magic_code\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the Magic Code schema handler.
 */
class MagicCodeStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   *
   * Remove this method when the fix lands in core:
   * https://www.drupal.org/project/drupal/issues/3005447
   */
  public function onEntityTypeUpdate(EntityTypeInterface $entity_type, EntityTypeInterface $original): void {
    parent::onEntityTypeUpdate($entity_type, $original);

    $entitySchema = $this->getEntitySchema($entity_type, TRUE);
    $schemaTable = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
    $schemaIndexes = $entitySchema[$schemaTable]['indexes'];
    foreach ($schemaIndexes as $indexName => $indexFields) {
      if (!$this->database->schema()->indexExists($schemaTable, $indexName)) {
        $this->database->schema()->addIndex($schemaTable, $indexName, $indexFields, $entitySchema[$schemaTable]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping): array {
    $schema = parent::getSharedTableFieldSchema($storage_definition, $table_name, $column_mapping);

    $entityType = $this->entityTypeManager->getDefinition($storage_definition->getTargetEntityTypeId());
    $fieldIndexes = $entityType->get('field_indexes');
    foreach ($fieldIndexes as $fieldName) {
      if ($fieldName == $storage_definition->getName()) {
        $this->addSharedTableFieldIndex($storage_definition, $schema);
      }
    }

    return $schema;
  }

}
