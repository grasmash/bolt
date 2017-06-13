<?php

namespace Acquia\Blt\Robo;

use Acquia\Blt\Robo\Common\Executor;
use Acquia\Blt\Robo\Config\ConfigAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Ramsey\Uuid\Uuid;
use Robo\Contract\ConfigAwareInterface;
use Tivie\OS\Detector;

/**
 *
 */
class AnalyticsManager implements ConfigAwareInterface, LoggerAwareInterface {

  use ConfigAwareTrait;
  use LoggerAwareTrait;

  /**
   * @var \Tivie\OS\Detector
   */
  protected $detector;

  /**
   * @var string
   */
  protected $uuid;

  /**
   * @var null|string
   */
  protected $machineUuid = NULL;

  /**
   * @var bool
   */
  protected $optOut = FALSE;

  /**
   * @var string
   */
  protected $segmentWriteKey;

  /**
   * @var \Acquia\Blt\Robo\Common\Executor
   */
  protected $executor;

  /**
   * AnalyticsManager constructor.
   */
  public function __construct(Executor $executor) {
    if (array_key_exists('BLT_NO_ANALYTICS', $_ENV) && $_ENV['BLT_NO_ANALYTICS']) {
      $this->optOut = TRUE;
    }
    else {
      $this->detector = new Detector();
      $this->executor = $executor;
      $this->segmentWriteKey = $this->getWriteKey();
    }
  }

  /**
   * Returns the publicly accessible write key for Segment.io.
   *
   * This is not intended to be secure. It is lightly and insecurely obfuscated.
   *
   * @see https://community.segment.com/t/m26sng/writekey-accessible-by-anyone
   * @see https://segment.com/docs/guides/getting-started/security-overview/
   */
  protected function getWriteKey() {
    $decrypt_key = 'ADO5mSeDg#oOcM;';
    $obfuscated_key = 'ED3w6XimZqFGBgHjhBY9jOV1SIn3+YNUIgyAs2lUlxXwDVE+UpcVYTo4eKZTCSCn';
    return openssl_decrypt($obfuscated_key, 'AES-128-CBC', $decrypt_key);
  }

  /**
   * Initializes analytics manager.
   *
   * Sets and transmits anonymous machine id.
   */
  public function initialize() {
    if ($this->optOut) {
      $this->logger->debug("BLT analytics are disabled.");
      return FALSE;
    }
    else {
      $this->logger->debug("Gathering analytics. You may disable analytics by setting the bash variable BLT_NO_ANALYTICS to 1.");
    }

    \Segment::init($this->segmentWriteKey);

    $this->setGitConfig();
    $uuid = $this->getConfig()->get('git.config.acquia-blt.application-id');
    if (is_null($uuid)) {
      $uuid = $this->createMachineUuid();
      $this->transmitIdentity($uuid);
    }
    $this->setMachineUuid($uuid);
  }

  /**
   * Track a specific event.
   *
   * @param string $event_id
   *   The ID of the event.
   * @param array $event_properties
   *   An arbitrary array of event-specific properties. Defaults to [].
   */
  public function trackEvent($event_id, $event_properties = []) {
    $properties = @$this->gatherEnvironmentData();
    $properties['event'] = $event_properties;
    \Segment::track(array(
      "userId" => $this->getMachineUuid(),
      "event" => $event_id,
      // We silence this intentionally to make analytics unobtrusive.
      // It's ok if something fails.
      "properties" => $properties,
    ));
  }

  /**
   * Gathers analytics regarding environment, blt, app config, etc.
   *
   * @return array
   *   An array of analytics..
   */
  protected function gatherEnvironmentData() {
    $data = [
      'source_app' => 'blt',
      'blt_version' => Blt::VERSION,
      'machine_id' => $this->getMachineUuid(),
      'project' => [
        // 'name' => $this->getConfigValue('project.human_name'),.
        'drupal' => [
          'version' => $this->getDrupalVersion(),
          'profile' => $this->getConfigValue('project.profile.name'),
        ],
        'features' => [
          'simplesamlphp' => $this->getConfig()->has('simplesamlphp'),
          'git-hooks' => file_exists($this->getConfigValue('repo.root') . '/.git/hooks/pre-commit'),
          'cloud-hooks' => file_exists($this->getConfigValue('repo.root') . '/hooks'),
        ],
      ],
      'environment' => [
        'php' => [
          'version' => phpversion(),
          'xdebug' => extension_loaded('xdebug'),
        ],
        'os' => [
          'family' => $this->detector->getFamily(),
          'type' => $this->detector->getType(),
          'kernel' => $this->detector->getKernelName(),
          'unix-like' => $this->detector->isUnixLike(),
          'windows-like' => $this->detector->isWindowsLike(),
        ],
        'shell' => $_SERVER['SHELL'],
      ],
      'products' => [
        'cloud' => [
          'enabled' => $this->gitRemotesContainString('acquia'),
          'sub' => $this->getAcquiaSubFromRemotes(),
        ],
        'pipelines' => [
          'enabled' => file_exists($this->getConfigValue('repe.root') . '/acquia-pipelines.yml'),
          'application-id' => $this->getConfigValue('git.config.acquia-pipelines.application-id'),
        ],
        'site-factory' => file_exists($this->getConfigValue('repo.root') . '/factory-hooks'),
        // @todo Check settings.php instead.
        'dev-desktop' => isset($_ENV['DEVDESKTOP_DRUPAL_SETTINGS_DIR']),
        'search' => FALSE,
        'lightning' => strtolower($this->getConfigValue('project.profile.name')) == 'lightning',
      ],
      'other-tools' => [
        'drupal-vm' => file_exists($this->getConfigValue('repo.root') . '/Vagrantfile'),
        'travis-ci' => file_exists($this->getConfigValue('repo.root') . '/.travis.yml'),
        'github' => $this->gitRemotesContainString('github'),
        'gitlab' => $this->gitRemotesContainString('gitlab'),
        'bitbucket' => $this->gitRemotesContainString('bitbucket'),
      ],
    ];

    return $data;
  }

  /**
   * Gets the Drupal core version being used. E.g., 8.0.0.
   *
   * @return string|null
   */
  protected function getDrupalVersion() {
    $composer_lock = json_decode(file_get_contents($this->getConfigValue('repo.root') . '/composer.lock'), TRUE);
    foreach ($composer_lock['packages'] as $key => $package) {
      if ($package['name'] == 'drupal/core') {
        return $package['version'];
      }
    }
    return NULL;
  }

  /**
   * Determines if any one of git remotes for this repo contain a given string.
   *
   * @return bool
   *   TRUE if at least one remote contains the needle.
   */
  protected function gitRemotesContainString($needle) {
    foreach ($this->getConfigValue('git.config.remote') as $name => $remote) {
      if (strstr(strtolower($remote['url']), $needle) !== FALSE) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gets the associated Acquia subscription name, if applicable.
   *
   * @return string|null
   */
  protected function getAcquiaSubFromRemotes() {
    // @todo Make this apply only to acquia subs.
    $pattern = '/(.+)@(.+):(.+)\.git/';
    foreach ($this->getConfigValue('git.config.remote') as $name => $remote) {
      if (preg_match($pattern, $remote['url'], $matches)) {
        return $matches[1];
      }
    }
    foreach ($this->getConfigValue('git.remotes') as $key => $url) {
      if (preg_match($pattern, $url, $matches)) {
        return $matches[1];
      }
    }
    return NULL;
  }

  /**
   * Gets $this->machineUuid.
   *
   * @return null|string
   */
  protected function getMachineUuid() {
    return $this->machineUuid;
  }

  /**
   * Sets $this->machineUuid.
   *
   * @param $uuid
   */
  protected function setMachineUuid($uuid) {
    $this->machineUuid = $uuid;
  }

  /**
   * Creates a new uuid for a machine. Writes it to local git config.
   *
   * @return string
   *   The new uuid.
   */
  protected function createMachineUuid() {
    $uuid = Uuid::uuid4()->toString();
    $this->writeMachineUuid($uuid);
    $this->logger->debug("Wrote machine id $uuid to .git/config.");

    return $uuid;
  }

  /**
   * Transmits anonymous machine uuid to Segment.io.
   *
   * @param string $uuid
   *   The machine uuid.
   */
  protected function transmitIdentity($uuid) {
    $properties = @$this->gatherEnvironmentData();
    \Segment::identify(array(
      "userId" => $uuid,
      "traits" => $properties,
    ));
  }

  /**
   * Writes anonymous machine uuid to local git repo config.
   *
   * @param string $uuid
   *   The UUID.
   *
   * @return bool
   *   TRUE if write operation was successful.
   */
  protected function writeMachineUuid($uuid) {
    $result = $this->executor->execute("git config acquia-blt.application-id $uuid")->run();

    return $result->wasSuccessful();
  }

  /**
   * Sets 'git.config' key in $this->config using local repo git config.
   */
  protected function setGitConfig() {
    $result = $this->executor->execute("git config -l")->run();
    $output = $result->getMessage();
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
      list($key, $value) = explode('=', $line);
      $this->getConfig()->set("git.config.$key", $value);
    }
  }

}
