<?php

namespace Acquia\Blt\Robo\Commands\Setup;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\RandomString;
use Robo\Contract\VerbosityThresholdInterface;

/**
 * Defines commands in the "setup:settings" namespace.
 */
class SettingsCommand extends BltTasks {

  protected $defaultBehatLocalConfigFile;
  protected $projectBehatLocalConfigFile;

  /**
   * This hook will fire for all commands in this command file.
   *
   * @hook init
   */
  public function initialize() {
    $this->defaultBehatLocalConfigFile = $this->getConfigValue('repo.root') . '/tests/behat/example.local.yml';
    $this->projectBehatLocalConfigFile = $this->getConfigValue('repo.root') . '/tests/behat/local.yml';
  }

  /**
   * Generates default settings files for Drupal and drush.
   *
   * @command setup:settings
   */
  public function generateSiteConfigFiles() {
    $this->taskFilesystemStack()
      ->copy($this->getConfigValue('blt.config-files.example-local'), $this->getConfigValue('blt.config-files.local'))
      ->stopOnFail()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    $multisites = $this->getConfigValue('multisites');
    foreach ($multisites as $multisite) {
      // Generate settings.php.
      $multisite_dir = $this->getConfigValue('docroot') . "/sites/$multisite";
      $project_default_settings_file = "$multisite_dir/default.settings.php";
      $project_settings_file = "$multisite_dir/settings.php";

      // Generate local.settings.php.
      $blt_local_settings_file = $this->getConfigValue('blt.root') . '/settings/default.local.settings.php';
      $default_local_settings_file = "$multisite_dir/settings/default.local.settings.php";
      $project_local_settings_file = "$multisite_dir/settings/local.settings.php";

      // Generate local.drushrc.php.
      $blt_local_drush_file = $this->getConfigValue('blt.root') . '/settings/default.local.drushrc.php';
      $default_local_drush_file = "$multisite_dir/default.local.drushrc.php";
      $project_local_drush_file = "$multisite_dir/local.drushrc.php";

      $this->taskFilesystemStack()
        ->chmod($multisite_dir, 0777)
        // @todo Might need to check that this file exists before chmoding it.
        ->chmod($project_settings_file, 0777)
        ->copy($project_default_settings_file, $project_settings_file)
        ->copy($blt_local_settings_file, $default_local_settings_file)
        ->copy($default_local_settings_file, $project_local_settings_file)
        ->copy($blt_local_drush_file, $default_local_drush_file)
        ->copy($default_local_drush_file, $project_local_drush_file)
        ->stopOnFail()
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
        ->run();

      $this->getConfig()->expandFileProperties($project_local_drush_file);

      $this->taskWriteToFile($project_settings_file)
        ->appendUnlessMatches('#vendor/acquia/blt/settings/blt.settings.php#', 'require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";')
        ->append(TRUE)
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
        ->run();

      $this->taskFilesystemStack()
        ->chmod($project_settings_file, 0644)
        ->stopOnFail()
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
        ->run();
    }
  }

  /**
   * Generates tests/behat/local.yml file for executing Behat tests locally.
   *
   * @command setup:behat
   */
  public function behat() {
    $this->say("Generating Behat configuration files...");
    $this->taskFilesystemStack()
      ->copy($this->defaultBehatLocalConfigFile, $this->projectBehatLocalConfigFile)
      ->stopOnFail()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    $this->getConfig()->expandFileProperties($this->projectBehatLocalConfigFile);
  }

  /**
   * Installs BLT git hooks to local .git/hooks directory.
   *
   * @command setup:git-hooks
   */
  public function gitHooks() {
    foreach (['pre-commit', 'commit-msg'] as $hook) {
      $this->installGitHook($hook);
    }
  }

  /**
   * Installs a given git hook.
   *
   * This symlinks the hook into the project's .git/hooks directory.
   *
   * @param string $hook
   *   The git hook to install. E.g., 'pre-commit'.
   */
  protected function installGitHook($hook) {
    if ($this->getConfigValue('git.hooks.' . $hook)) {
      $this->say("Installing $hook git hook...");
      $source = $this->getConfigValue('git.hooks.' . $hook) . "/$hook";
      $dest = $this->getConfigValue('repo.root') . "/.git/hooks/$hook";

      $this->taskFilesystemStack()
        ->mkdir($this->getConfigValue('repo.root') . '/.git/hooks')
        ->remove($dest)
        ->symlink($source, $dest)
        ->stopOnFail()
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
        ->run();
    }
    else {
      $this->say("Skipping installation of $hook git hook");
    }
  }

  /**
   * Writes a hash salt to ${repo.root}/salt.txt if one does not exist.
   *
   * @command setup:hash-salt
   *
   * @return int
   *   A CLI exit code.
   */
  public function hashSalt() {
    $hash_salt_file = $this->getConfigValue('repo.root') . '/salt.txt';
    if (!file_exists($hash_salt_file)) {
      $this->say("Writing hash salt to $hash_salt_file");
      $result = $this->taskWriteToFile($hash_salt_file)
        ->line(RandomString::string(55))
        ->run();

      return $result->wasSuccessful();
    }

    return TRUE;
  }

}
