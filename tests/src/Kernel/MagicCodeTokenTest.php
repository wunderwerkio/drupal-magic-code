<?php

declare(strict_types=1);

namespace Drupal\Tests\magic_code\Kernel;

use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\magic_code\MagicCodeManagerInterface;

/**
 * Tests the Magic Code Tokens in user emails.
 */
class MagicCodeTokenTest extends EntityKernelTestBase {

  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'image',
    'file',
    'magic_code',
    'magic_code_test',
  ];

  /**
   * The client.
   */
  protected Consumer $client;

  /**
   * The manager service.
   */
  protected MagicCodeManagerInterface $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('consumer');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('magic_code');
    $this->installConfig(['user', 'magic_code']);

    $this->drupalSetUpCurrentUser();

    $this->client = Consumer::create([
      'client_id' => 'test',
      'label' => 'test',
      'is_default' => TRUE,
    ]);
    $this->client->save();

    /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
    $configFactory = $this->container->get('config.factory');
    $mailConfig = $configFactory->getEditable('user.mail');

    $passwordResetConfig = $mailConfig->get('password_reset');
    $passwordResetConfig['body'] = $passwordResetConfig['body'] . PHP_EOL . '[user:magic-code-set-password]';
    $mailConfig->set('password_reset', $passwordResetConfig)->save();

    $registerConfig = $mailConfig->get('register_admin_created');
    $registerConfig['body'] = $registerConfig['body'] . PHP_EOL . '[user:magic-code-added-operation]';
    $mailConfig->set('register_admin_created', $registerConfig)->save();

    $this->manager = $this->container->get('magic_code.manager');
  }

  /**
   * Test the password reset magic code token replacement.
   */
  public function testPasswordResetToken() {
    $user = $this->drupalCreateUser();

    _user_mail_notify('password_reset', $user);

    $query = $this->entityTypeManager->getStorage('magic_code')->getQuery();
    $ids = $query->accessCheck(FALSE)->execute();

    $this->assertCount(1, $ids);

    $id = reset($ids);

    /** @var \Drupal\magic_code\Entity\MagicCodeInterface $codeEntity */
    $codeEntity = $this->entityTypeManager->getStorage('magic_code')->load($id);

    $this->assertEquals($codeEntity->getOperation(), 'set-password');
    $this->assertMailString('body', $codeEntity->label(), 1);
  }

  /**
   * Test the magic code tokens when added by other modules.
   */
  public function testModuleAddedOperations() {
    $user = $this->drupalCreateUser();

    _user_mail_notify('register_admin_created', $user);

    $query = $this->entityTypeManager->getStorage('magic_code')->getQuery();
    $ids = $query->accessCheck(FALSE)->execute();

    $this->assertCount(1, $ids);
    $id = reset($ids);

    /** @var \Drupal\magic_code\Entity\MagicCodeInterface $codeEntity */
    $codeEntity = $this->entityTypeManager->getStorage('magic_code')->load($id);

    $this->assertEquals($codeEntity->getOperation(), 'added-operation');
    $this->assertMailString('body', $codeEntity->label(), 1);
  }

}
