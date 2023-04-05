<?php

/**
 * @file
 * Token definitions for magic_code.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Implements hook_token_info().
 */
function magic_code_token_info() {
  $operations = _magic_code_get_available_tokens();
  $tokens = [];

  foreach ($operations as $operation) {
    $key = 'magic-code-' . $operation;

    $tokens[$key] = [
      'name' => new TranslatableMarkup('Magic Code - @operation', [
        '@operation' => $operation,
      ]),
      'description' => new TranslatableMarkup('A magic code with the operation "@operation"', [
        '@operation' => $operation,
      ]),
      'restricted' => TRUE,
    ];
  }

  return [
    'tokens' => [
      'user' => $tokens,
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function magic_code_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type == 'user' && !empty($data['user'])) {
    $operations = _magic_code_get_available_tokens();

    foreach ($tokens as $name => $original) {
      // Safety check.
      if (!str_contains($name, 'magic-code-')) {
        continue;
      }

      $operation = str_replace('magic-code-', '', $name);

      if (in_array($operation, $operations)) {
        $replacements[$original] = sprintf('[user:magic-code-%s_mail-only]', $operation);
      }
    }
  }

  return $replacements;
}
