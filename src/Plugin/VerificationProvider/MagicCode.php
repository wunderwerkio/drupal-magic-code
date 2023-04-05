<?php

declare(strict_types=1);

namespace Drupal\magic_code\Plugin\VerificationProvider;

use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\consumers\Negotiator;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\magic_code\MagicCodeManagerInterface;
use Drupal\magic_code\MagicCodeResult;
use Drupal\verification\Plugin\VerificationProviderBase;
use Drupal\verification\Result\VerificationResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Magic code verification provider plugin.
 *
 * @VerificationProvider(
 *   id = "magic_code",
 *   label = @Translation("Magic code"),
 * )
 */
class MagicCode extends VerificationProviderBase implements ContainerFactoryPluginInterface {

  const HEADER_MAGIC_CODE = 'X-Verification-Magic-Code';

  const UNHANDLED_NO_CONSUMER = 'magic_code_no_consumer_found';
  const ERR_INVALID_CODE = 'magic_code_invalid_code';

  /**
   * Construct new Magic Code provider plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected MagicCodeManagerInterface $magicCodeManager,
    protected Negotiator $negotiator,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('magic_code.manager'),
      $container->get('consumer.negotiator'),
      $container->get('logger.channel.magic_code'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function verifyOperation(Request $request, string $operation, AccountInterface $user, ?string $email = NULL): VerificationResult {
    $callback = $this->coreVerify($request);
    if ($callback instanceof VerificationResult) {
      return $callback;
    }

    return $callback(
      function (ConsumerInterface $consumer, string $code) use ($operation, $user, $email) {
        $result = $this->magicCodeManager->verify(
          $code,
          $operation,
          MagicCodeManagerInterface::VERIFY_MODE_OPERATION,
          $user,
          $consumer,
          $email,
        );

        if ($result === MagicCodeResult::Success) {
          return VerificationResult::ok();
        }

        return VerificationResult::err(
          'magic_code_' . $result->value,
        );
      }
    );
  }

  /**
   * {@inheritdoc}
   */
  public function verifyLogin(Request $request, string $operation, AccountInterface $user, ?string $email = NULL): VerificationResult {
    $callback = $this->coreVerify($request);
    if ($callback instanceof VerificationResult) {
      return $callback;
    }

    return $callback(
      function (ConsumerInterface $consumer, string $code) use ($operation, $user, $email) {
        $result = $this->magicCodeManager->verify(
          $code,
          $operation,
          MagicCodeManagerInterface::VERIFY_MODE_LOGIN,
          $user,
          $consumer,
          $email,
        );

        if ($result === MagicCodeResult::Success) {
          return VerificationResult::ok();
        }

        return VerificationResult::err(
          'magic_code_' . $result->value,
        );
      }
    );
  }

  /**
   * Common verification logic.
   *
   * This method implements the common verification logic
   * used by all verification methods.
   *
   * Specific verification is passed as a closure to the
   * return function of this method.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Closure|VerificationResult
   *   A closure that executes specific verification logic
   *   or a VerificationResult.
   */
  protected function coreVerify(Request $request) {
    $consumer = $this->negotiator->negotiateFromRequest($request);
    if (!$consumer) {
      $this->logger->error('Consumer could not be negotiated for request!');

      return VerificationResult::unhandled(self::UNHANDLED_NO_CONSUMER);
    }

    // No verification data found.
    if (!$request->headers->has(self::HEADER_MAGIC_CODE)) {
      return VerificationResult::unhandled();
    }

    $code = $request->headers->get(self::HEADER_MAGIC_CODE);
    if (!$code) {
      return VerificationResult::err(self::ERR_INVALID_CODE);
    }

    return function (\Closure $innerVerify) use ($consumer, $code) {
      return $innerVerify($consumer, $code);
    };
  }

}
