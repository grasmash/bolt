<?php

namespace Acquia\Blt\Robo\Commands\Deploy;

use Acquia\Blt\Robo\BltTasks;
use Robo\Contract\VerbosityThresholdInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

/**
 * Defines commands in the "deploy:update*" namespace.
 */
class UpdateCommand extends BltTasks {

  /**
   * Executes updates and imports config for all multisites.
   *
   * @command deploy:update:all
   */
  public function updateSites() {
    // Most sites store their version-controlled configuration in
    // /config/default. ACE internally sets the vcs configuration
    // directory to /config/default, so we use that.
    $this->config->set('cm.core.key', $this->getConfigValue('cm.core.deploy-key'));
    // Disable alias since we are targeting specific uri.
    $this->config->set('drush.alias', '');

    foreach ($this->getConfigValue('multisites') as $multisite) {
      return $this->updateSite($multisite);
    }
  }

  /**
   * Executes updates and imports config for a single site.
   *
   * @param string $site
   *
   * @return int
   *   The exit code of the most recently invoked command.
   */
  protected function updateSite($site) {
    $this->say("Deploying updates to $site...");
    $this->config->set('drush.uri', $site);

    $status_code = $this->invokeCommand('setup:config-import');
    if (!$status_code) {
      return $status_code;
    }
    $status_code = $this->invokeCommand('setup:toggle-modules');
    if (!$status_code) {
      return $status_code;
    }

    $this->say("Finished deploying updates to $site.");

    return $status_code;
  }

  /**
   * @command deploy:update:acsf
   */
  public function updateAcsf() {
    // drush @"${drush_alias}" --include=./drush acsf-tools-list | grep domains: -A 1 | grep 0: | sed -e 's/^[0: ]*//'
  }

}
