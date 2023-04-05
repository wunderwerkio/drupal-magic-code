<?php

declare(strict_types=1);

namespace Drupal\Tests\magic_code\Kernel;

use Drupal\consumers\Entity\Consumer;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\magic_code\MagicCodeManagerInterface;
use Drupal\magic_code\MagicCodeResult;

/**
 * Tests the Magic Code Manager.
 */
class MagicCodeManagerTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'image',
    'file',
    'magic_code',
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
    ]);
    $this->client->save();

    $this->manager = $this->container->get('magic_code.manager');
  }

  /**
   * Test magic code generation.
   */
  public function testMagicCodeCreation() {
    $operation = 'demo-operation';
    $user = \Drupal::currentUser();
    $now = \Drupal::time()->getRequestTime();
    $ttl = (int) $this->config('magic_code.settings')->get('code_ttl');

    $codeEntity = $this->manager->createNew($operation, $user, $this->client);

    $this->assertNotNull($codeEntity);
    $this->assertEquals($user->getEmail(), $codeEntity->getEmail());
    $this->assertEquals($user->id(), $codeEntity->getOwner()->id());
    $this->assertEquals($user->id(), $codeEntity->get('auth_user_id')->getString());
    $this->assertEquals($operation, $codeEntity->getOperation());
    $this->assertFalse($codeEntity->isRevoked());
    $this->assertEquals($now + $ttl, $codeEntity->get('expire')->getString());
    $this->assertMatchesRegularExpression('/^[A-Z1-9]{3}-[A-Z1-9]{3}$/', $codeEntity->label());

    // With explicit email.
    $email = 'explicit-email@example.com';
    $codeEntity = $this->manager->createNew($operation, $user, $this->client, $email);

    $this->assertEquals($email, $codeEntity->getEmail());
    $this->assertEquals($user->id(), $codeEntity->getOwner()->id());
  }

  /**
   * Test magic code verification.
   */
  public function testMagicCodeVerify() {
    $operation = 'demo-operation';
    $user = \Drupal::currentUser();
    $otherUser = $this->drupalCreateUser();
    $otherClient = Consumer::create(['client_id' => 'other_client']);
    $mode = MagicCodeManagerInterface::VERIFY_MODE_OPERATION;

    // Success.
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);
    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Success);

    // Verifying again must not work.
    // Code is revoked.
    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);

    // Wrong operation.
    $codeEntity = $this->manager->createNew($operation . '2', $user, $this->client);
    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);

    // Wrong user.
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);
    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $otherUser, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);

    // Wrong client.
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);
    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $user, $otherClient);
    $this->assertEquals($result, MagicCodeResult::Invalid);

    // Wrong email.
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);
    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $user, $this->client, 'other-email@example.com');
    $this->assertEquals($result, MagicCodeResult::Invalid);

    // Wrong code value.
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);
    $result = $this->manager->verify('ABC-123', $operation, $mode, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);
  }

  /**
   * Test flood protection by user.
   */
  public function testMagicCodeFloodUser() {
    $operation = 'register';
    $user = \Drupal::currentUser();
    $mode = MagicCodeManagerInterface::VERIFY_MODE_OPERATION;

    $codeEntity = $this->manager->createNew($operation, $user, $this->client);

    // Fail 4 times.
    for ($i = 0; $i < 4; ++$i) {
      $this->manager->verify('wrong', $operation, $mode, $user, $this->client);
    }

    // 5th time is successful -> PASS.
    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Success);

    // Restart with new token.
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);

    // Fail 5 times -> User is blocked.
    for ($i = 0; $i < 5; ++$i) {
      $this->manager->verify('wrong', $operation, $mode, $user, $this->client);
    }

    // 6th time is blocked -> FAIL
    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::BlockedByUser);

    // User is blocked, new code does not work.
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);
    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_LOGIN, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::BlockedByUser);
    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_OPERATION, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::BlockedByUser);
  }

  /**
   * Test flood protection by IP.
   */
  public function testMagicCodeFloodIp() {
    $operation = 'demo-operation';
    $user = \Drupal::currentUser();
    $mode = MagicCodeManagerInterface::VERIFY_MODE_OPERATION;

    // This creates 13 users with 4 tries per user,
    // resulting in 52 total tries for this IP.
    for ($i = 0; $i < 13; ++$i) {
      $innerUser = $this->drupalCreateUser();

      for ($j = 0; $j < 4; ++$j) {
        $this->manager->verify('wrong', $operation, $mode, $innerUser, $this->client);
      }
    }

    // IP is blocked.
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);
    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $user, $this->client);

    $this->assertEquals($result, MagicCodeResult::BlockedByIp);

    // Change IP.
    \Drupal::requestStack()->getCurrentRequest()->server->set('REMOTE_ADDR', '10.99.99.1');
    \Drupal::requestStack()->getCurrentRequest()->setTrustedProxies(['REMOTE_ADDR'], 0);

    $codeEntity = $this->manager->createNew($operation, $innerUser, $this->client);
    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $innerUser, $this->client);

    $this->assertEquals($result, MagicCodeResult::Success);
  }

  /**
   * Test combination of IP and User flood.
   */
  public function testMagicCodeFlood2() {
    $operation = 'demo-operation';
    $user = \Drupal::currentUser();
    $mode = MagicCodeManagerInterface::VERIFY_MODE_OPERATION;

    // Fail 5 times for each user, blocking 6th attempt for user.
    // Block IP for new user afer 50 failed attempts.
    $i = 0;
    while ($i < 50) {
      $innerUser = $this->drupalCreateUser();

      for ($j = 0; $j < 5; ++$j) {
        $this->manager->verify('wrong', $operation, $mode, $innerUser, $this->client);

        ++$i;
      }

      if ($i < 50) {
        // Inner user is blocked.
        $codeEntity = $this->manager->createNew($operation, $innerUser, $this->client);
        $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $innerUser, $this->client);

        $this->assertEquals($result, MagicCodeResult::BlockedByUser);

        ++$i;

        // Another user can verify.
        $codeEntity = $this->manager->createNew($operation, $user, $this->client);
        $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $user, $this->client);
        $this->assertEquals($result, MagicCodeResult::Success);
      }
    }

    // User is not blocked, but IP had 54 attempts.
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);
    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $user, $this->client);

    $this->assertEquals($result, MagicCodeResult::BlockedByIp);
  }

  /**
   * Test magic code revocation.
   */
  public function testMagicCodeRevoke() {
    $operation = 'demo-operation';
    $user = \Drupal::currentUser();
    $mode = MagicCodeManagerInterface::VERIFY_MODE_OPERATION;

    // Wrong code value.
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);
    $this->manager->revoke($codeEntity->id());

    $result = $this->manager->verify($codeEntity->label(), $operation, $mode, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);

    // Revoke multiple.
    $codeEntityOne = $this->manager->createNew($operation, $user, $this->client);
    $codeEntityTwo = $this->manager->createNew($operation, $user, $this->client);
    $this->manager->revokeMultiple([
      $codeEntityOne->id(),
      $codeEntityTwo->id(),
    ]);

    $result = $this->manager->verify($codeEntityOne->label(), $operation, $mode, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);

    $result = $this->manager->verify($codeEntityTwo->label(), $operation, $mode, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);
  }

  /**
   * Text expired magic code.
   */
  public function testMagicCodeExpiry() {
    $operation = 'register';
    $user = \Drupal::currentUser();
    $ttl = (int) $this->config('magic_code.settings')->get('code_ttl');
    $now = \Drupal::time()->getRequestTime();

    // Create a code that is already expired.
    \Drupal::requestStack()->getCurrentRequest()->server->set('REQUEST_TIME', $now - $ttl - 1);
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);

    // Reset request time back to normal.
    \Drupal::requestStack()->getCurrentRequest()->server->set('REQUEST_TIME', $now);

    // Nothing should work.
    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_LOGIN, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);
    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_OPERATION, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);
  }

  /**
   * Test login mode verification.
   */
  public function testLoginMode() {
    $operation = 'demo-operation';
    $user = \Drupal::currentUser();

    $codeEntity = $this->manager->createNew($operation, $user, $this->client);

    // Operation 'demo-operation' is not permitted to login.
    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_LOGIN, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);

    // Register is allowed.
    $operation = 'register';
    $codeEntity = $this->manager->createNew($operation, $user, $this->client);

    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_LOGIN, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Success);
    // Second login is not permitted.
    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_LOGIN, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);

    // But operation after login works.
    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_OPERATION, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Success);
    // Second operation does not.
    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_OPERATION, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);
  }

  /**
   * Test the login operation.
   *
   * This is a special case, because this operation
   * permits a login, but the login is the operation.
   */
  public function testLoginOperation() {
    $operation = 'login';
    $user = \Drupal::currentUser();

    $codeEntity = $this->manager->createNew($operation, $user, $this->client);

    // Allow login.
    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_LOGIN, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Success);
    // Login and code are revoked now.
    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_LOGIN, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);
    $result = $this->manager->verify($codeEntity->label(), $operation, MagicCodeManagerInterface::VERIFY_MODE_OPERATION, $user, $this->client);
    $this->assertEquals($result, MagicCodeResult::Invalid);
  }

}
