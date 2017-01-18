<?php

namespace Acquia\Blt\Robo\LocalEnvironment;

use Acquia\Blt\Robo\Common\ExecutorAwareInterface;
use Acquia\Blt\Robo\Common\ExecutorAwareTrait;
use Acquia\Blt\Robo\Common\IO;
use Acquia\Blt\Robo\Config\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;

/**
 * Class LocalEnvironment
 * @package Acquia\Blt\Robo\Common
 */
class LocalEnvironment implements ConfigAwareInterface, ExecutorAwareInterface {

  use IO;
  use ConfigAwareTrait;
  use ExecutorAwareTrait;

  /**
   * @return bool
   */
  public function repoRootExists() {
    return file_exists($this->getConfigValue('repo.root'));
  }

  /**
   * @return bool
   */
  public function docrootExists() {
    return file_exists($this->getConfigValue('docroot'));
  }

  /**
   * @return bool
   */
  public function drupalSettingsFileExists() {
     return file_exists($this->getConfigValue('drupal.settings_file'));
  }

  /**
   * @return bool
   */
  public function drupalSettingsFileIsValid() {
    $settings_file_contents = file_get_contents($this->getConfigValue('drupal.settings_file'));
    if (!strstr($settings_file_contents,
      '/../vendor/acquia/blt/settings/blt.settings.php')
    ) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks that Drupal is installed.
   */
  public function drupalIsInstalled() {
    // This will only run once per command. If Drupal is installed mid-command,
    // this value needs to be changed.
    if (!$this->getConfigValue('state.drupal.installed')) {
      $process = $this->getExecutor()->executeDrush("sqlq \"SHOW TABLES LIKE 'config'\"");
      $output = trim($process->getOutput());
      $installed = $process->isSuccessful() && $output == 'config';
      $this->getConfig()->set('state.drupal.installed', $installed);

      return $installed;
    }

    return $this->getConfigValue('state.drupal.installed');
  }

  /**
   * Checks if a given command exists on the system.
   *
   * @param $command string the command binary only. E.g., "drush" or "php".
   *
   * @return bool
   *   TRUE if the command exists, otherwise FALSE.
   */
  public static function commandExists($command) {
    exec("command -v $command >/dev/null 2>&1", $output, $exit_code);
    return $exit_code == 0;
  }

  public function behatIsConfigured() {
    return file_exists($this->getConfigValue('repo.root') . '/tests/behat/local.yml');
  }

  public function setDrushStatus() {
    if (!$this->getConfigValue('drush.status')) {
      $drush_status = json_decode($this->execDrush("status --format=json"), TRUE);
      $this->getConfig()->set('drush.status', $drush_status);
    }

    return $this;
  }
}
