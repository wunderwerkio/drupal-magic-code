<?php

declare(strict_types=1);

namespace Drupal\Tests\magic_code\Kernel;

use Drupal\consumers\Entity\Consumer;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\magic_code\MagicCodeManagerInterface;
use Drupal\verification\Service\RequestVerifier;
use Drupal\Tests\verification\Traits\VerificationTestTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the Magic Code Verification Provider.
 */
class VerificationProviderTest extends EntityKernelTestBase {

  use VerificationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'image',
    'file',
    'magic_code',
    'verification',
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
   * The request verifier service.
   */
  protected RequestVerifier $verifier;

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

    $this->manager = $this->container->get('magic_code.manager');
    $this->verifier = $this->container->get('verification.request_verifier');
  }

  /**
   * Test the verification provider plugin.
   */
  public function testVerificationProvider() {
    $operation = 'demo-operation';
    $user = \Drupal::currentUser();

    $codeEntity = $this->manager->createNew($operation, $user, $this->client);

    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Magic-Code', $codeEntity->label());
    $this->assertVerificationOk($this->verifier->verifyOperation($request, $operation, $user));
    $this->assertVerificationErr($this->verifier->verifyOperation($request, $operation, $user), 'magic_code_invalid');
  }

  /**
   * Test plugin with login and operation.
   */
  public function testWithPreceedingLogin() {
    // This operation must permit login.
    // See config at magic_code.settings.login_permitted_operations.
    $operation = 'set-password';
    $user = $this->drupalCreateUser();

    $codeEntity = $this->manager->createNew($operation, $user, $this->client);

    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Magic-Code', $codeEntity->label());

    $this->assertVerificationOk($this->verifier->verifyLogin($request, $operation, $user));
    user_login_finalize($user);
    $this->assertVerificationErr($this->verifier->verifyLogin($request, $operation, $user), 'magic_code_invalid');

    $this->assertVerificationOk($this->verifier->verifyOperation($request, $operation, $user));
    $this->assertVerificationErr($this->verifier->verifyOperation($request, $operation, $user), 'magic_code_invalid');
  }

}
