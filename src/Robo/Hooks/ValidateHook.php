<?php

namespace Acquia\Blt\Robo\Hooks;

use Acquia\Blt\Robo\Common\IO;
use Acquia\Blt\Robo\Config\ConfigAwareTrait;
use Acquia\Blt\Robo\Inspector\InspectorAwareInterface;
use Acquia\Blt\Robo\Inspector\InspectorAwareTrait;
use Consolidation\AnnotatedCommand\CommandData;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Robo\Contract\ConfigAwareInterface;

/**
 * This class provides hooks that validate configuration or state.
 *
 * These hooks should not directly provide user interaction. They should throw
 * and exception if a required condition is not met.
 *
 * Typically, each validation hook has an accompanying interact hook (which
 * runs prior to the validation hook). The interact hooks provide an
 * opportunity to the user to resolve the invalid configuration prior to an
 * exception being thrown.
 *
 * @see https://github.com/consolidation/annotated-command#validate-hook
 */
class ValidateHook implements ConfigAwareInterface, LoggerAwareInterface, InspectorAwareInterface {

  use ConfigAwareTrait;
  use LoggerAwareTrait;
  use InspectorAwareTrait;
  use IO;

  /**
   * Validates that the Drupal docroot exists.
   *
   * @hook validate @validateDocrootIsPresent
   */
  public function validateDocrootIsPresent(CommandData $commandData) {
    if (!$this->getInspector()->isDocrootPresent()) {
      throw new \Exception("Unable to find Drupal docroot.");
    }
  }

  /**
   * Validates that the repository root exists.
   *
   * @hook validate @validateRepoRootIsPresent
   */
  public function validateRepoRootIsPresent(CommandData $commandData) {
    if (empty($this->getInspector()->isRepoRootPresent())) {
      throw new \Exception("Unable to find repository root.");
    }
  }

  /**
   * Validates that Drupal is installed.
   *
   * @hook validate @validateDrupalIsInstalled
   */
  public function validateDrupalIsInstalled(CommandData $commandData) {
    if (!$this->getInspector()
      ->isDrupalInstalled()
    ) {

      throw new \Exception("Drupal is not installed");
    }
  }

  /**
   * Checks active settings.php file.
   *
   * @hook validate @validateSettingsFileIsValid
   */
  public function validateSettingsFileIsValid(CommandData $commandData) {
    if (!$this->getInspector()
      ->isDrupalSettingsFilePresent()
    ) {
      throw new \Exception("Could not find settings.php for this site.");
    }

    if (!$this->getInspector()->isDrupalSettingsFileValid()) {
      throw new \Exception("BLT settings are not included in settings file.");
    }
  }

  /**
   * Validates that Behat is properly configured on the local machine.
   *
   * @hook validate @validateBehatIsConfigured
   */
  public function validateBehatIsConfigured(CommandData $commandData) {
    if (!$this->getInspector()->isBehatConfigured()) {
      throw new \Exception("Behat is not properly configured properly. Please run `blt doctor` to diagnose the issue.");
    }
  }

  /**
   * Validates that MySQL is available.
   *
   * @hook validate @validateMySqlAvailable
   */
  public function validateMySqlAvailable() {
    if (!$this->getInspector()->isMySqlAvailable()) {
      // @todo Prompt to fix.
      throw new \Exception("MySql is not available. Please run `blt doctor` to diagnose the issue.");
    }
  }

  /**
   * Validates that current PHP process is being executed inside of the VM.
   *
   * @hook validate validateInsideVm
   */
  public function validateInsideVm() {
    if ($this->getInspector()->isDrupalVmLocallyInitialized() && !$this->getInspector()->isVmCli()) {
      throw new \Exception("You must run this command inside Drupal VM, or else do not use Drupal VM at all. Execute `vagrant ssh` and then execute the command, or else change drush.aliases.local in blt/project.local.yml.");
    }
  }

}
