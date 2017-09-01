<?php

namespace Drupal\example_module\Plugin\migrate\source;

use Drupal\migrate_source_csv\Plugin\migrate\source\CSV as SourceCSV;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateException;
use Drupal\Core\Site\Settings;
use phpseclib\Net\SFTP;


require_once DRUPAL_ROOT . '/core/includes/file.inc';

/**
 * Migration source for downloading CSV files via SFTP.
 *
 * This source extends the CSV source introduced by the migrate_source_csv
 * module. This plugin simply sees if FTP information is provided in source
 * configuration. If FTP information is provided, then we use it to download
 * the remote file by SFTP to a temporary location and then pass the path to
 * the temporary file to the CSV plugin.
 *
 * @MigrateSource(
 *   id = "migrate_example_source_remote_csv"
 * )
 */
class MigrateExampleSourceRemoteCSV extends SourceCSV {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    // Prepare connection parameters.
    if (!empty($configuration['sftp'])) {
      // A settings key must be specified.
      //
      // We use the settings key to get FTP configuration from $settings.
      if (!isset($configuration['sftp']['settings'])) {
        throw new MigrateException('Parameter "sftp/settings" not defined for Remote CSV source plugin.');
      }
      // Merge plugin settings with global settings.
      $configuration['sftp'] += static::getSFTPConfig($configuration['sftp']['settings']);
      // We simply download the remote CSV file to a temporary path and set
      // the temporary path to the parent CSV plugin.
      $configuration['path'] = $this->downloadFile($configuration['sftp']);
    }
    // If the file downloaded successfully with SFTP, then the "path" parameter
    // will be populated and the parent plugin will detect the CSV file.
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * Returns SFTP connection configuration from $settings['sftp'][$key].
   *
   * @param string $key
   *   The settings key.
   *
   * @return array
   *   Connection configuration.
   */
  public static function getSFTPConfig($key) {
    $conn_config = Settings::get('sftp', []);
    if (!isset($conn_config[$key])) {
      throw new MigrateException("FTP configuration must be set in \$settings[sftp][$key].");
    }
    return $conn_config[$key];
  }

  /**
   * Returns an SFTP connection with the given configuration.
   *
   * @param array $conn_config
   *   Connection parameters.
   *
   * @return SFTP
   *   The SFTP Connection.
   */
  public static function getSFTPConnection(array $conn_config) {
    static $connections;
    $key = $conn_config['server'];
    // We see if a connection for the given settings key is already created.
    // This avoids creating multiple parallel connections to the same server.
    if (!is_array($connections) || !isset($connections[$key])) {
      // Merge with default settings.
      // For example, if the SFTP settings in the source configuration do not
      // have a "port", then we use port 22 by default.
      $conn_config = $conn_config + [
        'port' => 22,
      ];
      // Required config parameters must be set.
      foreach (['server', 'username', 'password', 'port'] as $param) {
        if (!isset($conn_config[$param])) {
          throw new MigrateException('Required SFTP parameter ' . $param . ' not defined.');
        }
      }
      // Establish SFTP connection.
      $sftp = new SFTP($conn_config['server'], $conn_config['port']);
      if (TRUE !== $sftp->login($conn_config['username'], $conn_config['password'])) {
        throw new MigrateException('Cannot connect to SFTP server with the given credentials.');
      }
      // If the connection does not exist, we create it and cache it.
      $connections[$key] = $sftp;
    }
    // Return the connection corresponding to the SFTP settings key.
    return $connections[$key];
  }

  /**
   * Downloads a file from SFTP Server.
   *
   * @param array $conn_config
   *   Connection configuration.
   *
   * @throws MigrateException
   *   If something goes wrong.
   *
   * @return string
   *   Path to local cached version of the remote file.
   */
  protected function downloadFile(array $conn_config) {
    // Remote file path must be specified.
    // Without knowing the remote file path, we cannot download the file!
    if (empty($conn_config['path'])) {
      throw new MigrateException('Required parameter "path" not defined.');
    }

    // Prepare to download file to a temporary directory.
    $path_remote = $conn_config['path'];
    $basename = basename($path_remote);
    $path_local = file_directory_temp() . '/' . $basename;

    // Download file by SFTP.
    $sftp = static::getSFTPConnection($conn_config);
    if (!$sftp->get($path_remote, $path_local)) {
      throw new MigrateException('Cannot download remote file ' . $basename . ' by SFTP.');
    }
    // Return path to the local of the file.
    // This will in turn be passed to the parent CSV plugin.
    return $path_local;
  }

}
