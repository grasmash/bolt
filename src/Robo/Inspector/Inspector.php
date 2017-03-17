<?php

namespace Acquia\Blt\Robo\Inspector;

use Acquia\Blt\Robo\Common\Executor;
use Acquia\Blt\Robo\Common\IO;
use Acquia\Blt\Robo\Config\ConfigAwareTrait;
use Acquia\Blt\Robo\Config\YamlConfig;
use Acquia\Blt\Robo\Tasks\BltTasks;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Robo\Common\BuilderAwareTrait;
use Robo\Contract\BuilderAwareInterface;
use Robo\Contract\ConfigAwareInterface;

/**
 * Class Inspector.
 *
 * @package Acquia\Blt\Robo\Common
 */
class Inspector implements BuilderAwareInterface, ConfigAwareInterface, LoggerAwareInterface {

  use BuilderAwareTrait;
  use ConfigAwareTrait;
  use LoggerAwareTrait;
  use IO;

  /** @var Executor */
  protected $executor;

  /**
   * Inspector constructor.
   *
   * @param \Acquia\Blt\Robo\Common\Executor $executor
   */
  public function __construct(Executor $executor) {
    $this->executor = $executor;
  }

  /**
   * @return bool
   */
  public function isRepoRootPresent() {
    return file_exists($this->getConfigValue('repo.root'));
  }

  /**
   * @return bool
   */
  public function isDocrootPresent() {
    return file_exists($this->getConfigValue('docroot'));
  }

  /**
   * @return bool
   */
  public function isDrupalSettingsFilePresent() {
    return file_exists($this->getConfigValue('drupal.settings_file'));
  }

  /**
   * @return bool
   */
  public function isDrupalSettingsFileValid() {
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
  public function isDrupalInstalled() {
    // This will only run once per command. If Drupal is installed mid-command,
    // this value needs to be changed.
    if (is_null($this->getConfigValue('state.drupal.installed'))) {
      $installed = $this->getDrupalInstalled();
      $this->setStateDrupalInstalled($installed);
    }

    return $this->getConfigValue('state.drupal.installed');
  }

  public function isMySqlAvailable() {
    $result = $this->executor->drush("sqlq \"SHOW DATABASES\"")->run();
    if (!$result->wasSuccessful()) {
      $this->logger->info("MySQL is not available.");
    }
    return $result->wasSuccessful();
  }

  /**
   * @return bool
   */
  protected function getDrupalInstalled() {
    $result = $this->executor->drush("sqlq \"SHOW TABLES LIKE 'config'\"")->run();
    $output = trim($result->getOutputData());
    $installed = $result->wasSuccessful() && $output == 'config';

    return $installed;
  }

  /**
   * @param $installed
   *
   * @return $this
   */
  protected function setStateDrupalInstalled($installed) {
    $this->getConfig()->set('state.drupal.installed', $installed);

    return $this;
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

  public function getLocalBehatConfig() {
    $behat_local_config_file = $this->getConfigValue('repo.root') . '/tests/behat/local.yml';
    $behat_local_config = new YamlConfig($behat_local_config_file, $this->getConfig()->toArray());

    return $behat_local_config;
  }

  public function getBehatConfigFiles() {
    $behat_local_config = $this->getLocalBehatConfig();

    return [
      $behat_local_config->get('local.extensions.Drupal\DrupalExtension.drupal.drupal_root'),
      $behat_local_config->get('local.suites.default.paths.features'),
      $behat_local_config->get('local.suites.default.paths.bootstrap'),
    ];
  }

  public function filesExist($files) {
    foreach ($files as $file) {
      if (!file_exists($file)) {
        $this->logger->warning("Required file $file does not exist.");
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   *
   */
  public function isBehatConfigured() {
    $local_behat_config = $this->getLocalBehatConfig();
    if ($this->getConfigValue('project.local.uri') != $local_behat_config->get('local.extensions.Behat\MinkExtension.base_url')) {
      $this->logger->warning('project.local.uri in project.yml does not match local.extensions.Behat\MinkExtension.base_url in local.yml.');
      $this->logger->warning('project.local.uri = ' . $this->getConfigValue('project.local.uri'));
      $this->logger->warning('local.extensions.Behat\MinkExtension.base_url = ' . $local_behat_config->get('local.extensions.Behat\MinkExtension.base_url'));
      return FALSE;
    }

    if ($this->getConfigValue('behat.run-server')) {
      if ($this->getConfigValue('behat.server-url') != $this->getConfigValue('project.local.uri')) {
        $this->logger->warning("behat.run-server is enabled, but the server URL does not match Drupal's base URL.");
        $this->logger->warning('project.local.uri = ' . $this->getConfigValue('project.local.uri'));
        $this->logger->warning('behat.server-url = ' . $this->getConfigValue('behat.server-url'));
        $this->logger->warning('local.extensions.Behat\MinkExtension.base_url = ' . $local_behat_config->get('local.extensions.Behat\MinkExtension.base_url'));

        return FALSE;
      }
    }

    if (!$this->areBehatConfigFilesPresent()) {
      return FALSE;
    }

    return TRUE;
  }

  public function areBehatConfigFilesPresent() {
    return $this->filesExist($this->getBehatConfigFiles());
  }

  /**
   *
   */
  public function setDrushStatus() {
    if (!$this->getConfigValue('state.drush.status')) {
      $drush_status = json_decode($this->execDrush("status --format=json"),
        TRUE);
      $this->getConfig()->set('state.drush.status', $drush_status);
    }

    return $this;
  }

  public function isPhantomJsConfigured() {
    return $this->isPhantomJsRequired() && $this->isPhantomJsScriptConfigured() && $this->isPhantomJsBinaryPresent();
  }

  public function isPhantomJsRequired() {
    $result = $this->executor->execute("grep 'jakoch/phantomjs-installer' composer.json");
    return $result->wasSuccessful();
  }

  public function isPhantomJsScriptConfigured() {
    $result = $this->executor->execute("grep installPhantomJS composer.json");

    return $result->wasSuccessful();
  }

  public function isPhantomJsBinaryPresent() {
    return file_exists("{$this->getConfigValue('composer.bin')}/phantomjs");
  }
}