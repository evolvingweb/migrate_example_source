# Migrate Example: Writing a custom Migrate Source Plugin

When content keep changing during migrations can be tedious download the content from the server every time to we want to bring the new data. In this article we will write a source plugin that will allow us to automatically download the content from a remote server via SFTP before to process the migration.

## The Problem

Say we have the credentials of a server given by the client to download the a CSV file with the content and we need to download the content, this content keep changing on dially basis, so the migration needs to run once a day to get new data.

## Before We Start

The plugin must be placed in a custom module, if you haven't experience writing custom modules you can start reading this article: 

* [Creating custom modules](https://www.drupal.org/docs/8/creating-custom-modules) 

And if you are new to migrations in Drupal 8, I recommend you to start first with the following articles:

* [Drupal 8 Migration: Migrating Basic Data (Part 1)](https://evolvingweb.ca/blog/drupal-8-migration-migrating-basic-data-part-1)
* [Drupal 8 Migration: Migrating Taxonomy Term References (Part 2)](https://evolvingweb.ca/blog/drupal-8-migration-migrating-taxonomy-term-references-part-2)
* [Drupal 8 Migration: Migrating Files / Images (Part 3)](https://evolvingweb.ca/blog/drupal-8-migration-migrating-files-images-part-3)

## The Plan 

The goal is avoid downloading the file manually every time that we will run the migration so we need a way to doing this automatically,  The plan is create a source plugin which  will download the CSV file from the remote server and leave it in a temporal location. 

Once we have this file locally we will let the [Migrate Source CSV](https://www.drupal.org/project/migrate_source_csv) take care of the migration.

## The Source Migrate Plugin.

Let's create a custom module and call it `migrate_example_source` and create the php class inside this module in the following location:

```php
/src/Plugin/migrate/source/
```              

Let's call it `MigrateExampleSourceRemoteCSV.php` and will contain this basic code:

```php
namespace Drupal\migrate_source_csv\Plugin\migrate\source;

use Drupal\migrate_source_csv\Plugin\migrate\source\CSV as SourceCSV;

/**
 * @MigrateSource(
 *   id = "migrate_example_source_remote_csv"
 * )
 */
class MigrateExampleSourceRemoteCSV extends SourceCSV {
}
```

Adding the annotation `@MigrateSource` will allow to Migrate Module automatically find our plugin so this part is important, our plugin will download the sources and after will let  the CSV plugin to handle the migration, so we will extend CSV plugin and add the code to download the source.

Let's add the code to download the files in the plugin constructor.


```php

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    // Prepare connection parameters.
    if (!empty($configuration['sftp'])) {
      // A settings key must be specified.
      // We use the settings key to get FTP configuration from $settings.
      if (!isset($configuration['sftp']['settings'])) {
        throw new MigrateException('Parameter "sftp/settings" not defined for Remote CSV source plugin.');
      }
      // Merge plugin settings with global settings.
      $configuration['sftp'] += Settings::get('sftp', []);
      // We simply download the remote CSV file to a temporary path and set
      // the temporary path to the parent CSV plugin.
      $configuration['path'] = $this->downloadFile($configuration['sftp']);
    }
    // If the file downloaded successfully with SFTP, then the "path" parameter
    // will be populated and the parent plugin will detect the CSV file.
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }
```

In the construct we are getting the SFTP credentials from the settings.php file, the credentials should look like this:

```php
// Settings.php 
$settings['sftp'] = array(
  'default' => [
    'server' => 'ftpserver.ltd',
    'username' => 'username',
    'password' => 'password',
    'port' => '22',
  ],
);
``` 

Once we have the credentials of the FTP server we use the `$this->downloadFile()` method to download the file, this method contain the following code:

```php
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
```

This method create a SFTP connection, download the file and return the path so the CSV plugin can continue the migration, the code to create the SFTP (`static::getSFTPConnection`) connection is

```php
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
```
In the code we are using the [phpseclib](https://github.com/phpseclib/phpseclib) so we need to include it in our composer file using:

```php
composer require phpseclib/phpseclib
```
And we need to include it our class adding this in the top of our class (just below the namespace statement):

```php
use phpseclib\Net\SFTP
```

And that's it,  our plugin will download the CSV file and pass the path to the `Migrate Source CSV`, Finally, to use our plugin in a migration we will do it in this way:


```yaml
id: migrate_example_content
label: 'Example content'
dependencies:
  enforced:
    module:
      - migrate_example_source
source:
  plugin: migrate_example_source_remote_csv
  # Settings used from our plugin.
  sftp:
    settings: sftp
    path: "/path/to/file/example_content.csv"
  track_changes: true
  # Settings used from the Migrate Source CSV plugin.
  header_row_count: 1
  keys:
    - id
process:
  title: title
  body: body
destination:
  plugin: 'entity:node'

```

If you want to see how the code look you there is a repo with all what we did here: [migrate_example_source](https://github.com/evolvingweb/migrate_example_source) 
