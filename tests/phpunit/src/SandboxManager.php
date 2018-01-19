<?php

namespace Acquia\Blt\Tests;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 *
 */
class SandboxManager {

  /** @var \Symfony\Component\Filesystem\Filesystem*/
  protected $fs;
  protected $bltDir;
  protected $sandboxMaster;
  protected $sandboxInstance;
  protected $dbDumpDir;
  /** @var \Symfony\Component\Console\Output\ConsoleOutput*/
  protected $output;
  protected $tmp;

  public function __construct() {
    $this->output = new ConsoleOutput();
    $this->fs = new Filesystem();
    $this->tmp = sys_get_temp_dir();
    $this->sandboxMaster = $this->tmp . "/blt-sandbox-master";
    $this->sandboxInstance = $this->tmp . "/blt-sandbox-instance";
    $this->bltDir = realpath(dirname(__FILE__) . '/../../../');
    $this->dbDumpDir = $this->tmp . "/blt-sandbox-dumps";
  }

  public function bootstrap() {
    $this->output->writeln("Bootstrapping BLT testing framework...");
    $recreate_master = getenv('BLT_RECREATE_SANDBOX_MASTER');
    if ($recreate_master) {
      $this->output->writeln("<comment>To prevent recreation of sandbox master on each bootstrap, set BLT_RECREATE_SANDBOX_MASTER=0</comment>");
      $this->createSandboxMaster();
      $this->createDbDumpDir();
    }
    else {
      $this->output->writeln("<comment>Skipping master sandbox creation, BLT_RECREATE_SANDBOX_MASTER is disabled.");
    }
  }

  /**
   * Creates a new master sandbox.
   */
  public function createSandboxMaster() {
    $this->output->writeln("Creating master sandbox in <comment>{$this->sandboxMaster}</comment>...");
    $fixture = $this->bltDir . "/tests/phpunit/fixtures/sandbox";
    $this->fs->remove($this->sandboxMaster);
    $this->removeSandboxInstance();
    $this->fs->mirror($fixture, $this->sandboxMaster);
    $this->updateSandboxMasterBltRepoSymlink();
    $this->installSandboxMasterDependencies();
  }

  /**
   *
   */
  public function removeSandboxInstance() {
    $this->debug("\nRemoving sandbox instance...");
    $this->makeSandboxInstanceWritable();
    $this->fs->remove($this->sandboxInstance);
  }

  public function debug($message) {
    if (getenv('BLT_PRINT_COMMAND_OUTPUT')) {
      $this->output->writeln($message);
    }
  }

  public function makeSandboxInstanceWritable() {
    $sites_dir = $this->sandboxInstance . "/docroot/sites";
    if (file_exists($sites_dir)) {
      $this->fs->chmod($sites_dir, 0755, 0000, TRUE);
    }
  }

  /**
   * @return string
   */
  public function getDbDumpDir() {
    return $this->dbDumpDir;
  }

  /**
   * Creates a new sandbox instance using master as a reference.
   *
   * This will not overwrite existing files. Will delete files in destination
   * that are not in source.
   */
  public function refreshSandboxInstance() {
    try {
      $this->makeSandboxInstanceWritable();
      $this->copySandboxMasterToInstance();
      chdir($this->sandboxInstance);
    }
    catch (\Exception $e) {
      $this->replaceSandboxInstance();
    }
  }

  /**
   * @param $options
   */
  protected function copySandboxMasterToInstance($options = [
    'delete' => TRUE,
    'override' => FALSE,
  ]) {
    $this->debug("\nCopying sandbox master to sandbox instance...");
    $this->fs->mirror($this->sandboxMaster, $this->sandboxInstance, NULL,
      $options);
  }

  /**
   * Overwrites all files in sandbox instance.
   */
  public function replaceSandboxInstance() {
    $this->removeSandboxInstance();
    $this->copySandboxMasterToInstance();
  }

  /**
   * @return mixed
   */
  public function getSandboxInstance() {
    return $this->sandboxInstance;
  }

  /**
   * @return string
   */
  public function getSandboxMaster() {
    return $this->sandboxMaster;
  }

  protected function createDbDumpDir() {
    $this->fs->remove($this->dbDumpDir);
    $this->fs->mkdir($this->dbDumpDir);
  }

  protected function updateSandboxMasterBltRepoSymlink() {
    $composer_json_path = $this->sandboxMaster . "/composer.json";
    $composer_json_contents = json_decode(file_get_contents($composer_json_path));
    $composer_json_contents->repositories->blt->url = $this->bltDir;
    $this->fs->dumpFile($composer_json_path,
      json_encode($composer_json_contents, JSON_PRETTY_PRINT));
  }

  protected function installSandboxMasterDependencies() {
    $command = '';
    $drupal_core_version = getenv('DRUPAL_CORE_VERSION');
    if ($drupal_core_version && $drupal_core_version != 'default') {
      $command .= 'composer require "drupal/core:' . $drupal_core_version . '" --no-update --no-interaction && ';
    }
    $command .= 'composer install --prefer-dist --no-progress --no-suggest';

    $process = new Process($command, $this->sandboxMaster);
    $process->setTimeout(60 * 60);
    $process->run(function ($type, $buffer) {
      $this->output->write($buffer);
    });
  }

}