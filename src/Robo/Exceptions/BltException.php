<?php

namespace Acquia\Blt\Robo\Exceptions;

use Acquia\Blt\Robo\AnalyticsManager;

/**
 * Class BltException.
 *
 * @package Acquia\Blt\Robo\Exceptions
 */
class BltException extends \Exception {

  /**
   * @var \Acquia\Blt\Robo\AnalyticsManager
   */
  protected $analyticsManager;

  /**
   *
   */
  public function __construct(
    $message = "",
    $code = 0,
    \Throwable $previous = NULL,
    AnalyticsManager $anaytics_manager
  ) {
    $this->analyticsManager = $anaytics_manager;
    parent::__construct($message, $code, $previous);
    $this->transmitAnalytics($message, $code);
  }

  /**
   * Transmit anonymous data about Exception.
   */
  protected function transmitAnalytics($message, $code) {
    $this->analyticsManager->trackEvent('BltException' . $this->getMessage(), [
      'code' => $code,
      'message' => $message,
    ]);
  }


}
