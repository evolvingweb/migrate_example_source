# Migrate Example: Writing a custom Migrate Source Plugin

When working with migrations where the source files keep changing at regular intervals (say, nightly), it can get really tedious to download the updated source files manually before running migrations. In this article, we will write a source plugin based on the CSV source plugin which will allow us to automatically download CSV files from a remote server via SFTP before running migrations.

## The Problem

In a project we worked on recently, the requirements were moreover as follows:

* CSV files are updated by a PowerShell script every night on the client's server.
* These CSV files are accessible via SFTP.

Our task is to download the CSV source files over SFTP and to use them as our migration source.

## Before We Start

* This articles assumes that you can write custom modules. If you never written a custom module, you can try reading this article on [creating custom modules](https://www.drupal.org/docs/8/creating-custom-modules).
* It is assumed that you have working knowledge of migrations in Drupal 8. If you are new to Drupal 8 migrations, I recommend you to start by reading these articles first:
  * [Drupal 8 Migration: Migrating Basic Data (Part 1)](https://evolvingweb.ca/blog/drupal-8-migration-migrating-basic-data-part-1)
  * [Drupal 8 Migration: Migrating Taxonomy Term References (Part 2)](https://evolvingweb.ca/blog/drupal-8-migration-migrating-taxonomy-term-references-part-2)
  * [Drupal 8 Migration: Migrating Files / Images (Part 3)](https://evolvingweb.ca/blog/drupal-8-migration-migrating-files-images-part-3)

## The Plan 

The goal is to avoid downloading the file manually every time we run our migrations. So we need a way to doing this automatically everytime we execute a migration. To achieve this, we create a custom source plugin extending the CSV plugin provided by the [Migrate Source CSV](https://www.drupal.org/project/migrate_source_csv) module, which will download CSV files from a remote server and pass it to the CSV plugin to process them.

## The Source Migrate Plugin

To start, let's create a custom module and call it `migrate_example_source` and implement a custom migrate source plugin by creating a PHP class inside it at the following location:

```php
/src/Plugin/migrate/source/MigrateExampleSourceRemoteCSV.php
```              
We start implementing the class by simply extending the CSV plugin provided by the `migrate_source_csv` module:

```php
namespace Drupal\migrate_source_csv\Plugin\migrate\source;

use Drupal\migrate_source_csv\Plugin\migrate\source\CSV as SourceCSV;
use phpseclib\Net\SFTP

/**
 * @MigrateSource(
 *   id = "migrate_example_source_remote_csv"
 * )
 */
class MigrateExampleSourceRemoteCSV extends SourceCSV {}
```

Adding the annotation `@MigrateSource` is very important because that is what will make the `migrate` module detect our source plugin. In our plugin, we use the [phpseclib/phpseclib](https://github.com/phpseclib/phpseclib) libraries to make SFTP connections. Hence, we need to include the libraries in our project by running the following command in the Drupal root:

```
composer require phpseclib/phpseclib
```

Our plugin will download the source CSV file and will simply pass it to the CSV plugin to do the rest. We do the download when the plugin is being instantiated like this:

```php
  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    // If SFTP connection parameters are present.
    if (!empty($configuration['sftp'])) {
      // A settings key must be specified.
      // We use the settings key to get SFTP configuration from $settings.
      if (!isset($configuration['sftp']['settings'])) {
        throw new MigrateException('Parameter "sftp/settings" not defined for Remote CSV source plugin.');
      }
      // Merge plugin settings with global settings.
      $configuration['sftp'] += Settings::get('sftp', []);
      // We simply download the remote CSV file to a temporary path and set
      // the temporary path to the parent CSV plugin.
      $configuration['path'] = $this->downloadFile($configuration['sftp']);
    }
    // Having downloaded the remote CSV, we simply pass the call to the parent plugin.
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }
```

In the constructor we are using global SFTP credentials with `Settings::get()`. We need to define the credentials in `settings.php` like this:

```php
$settings['sftp'] = array(
  'default' => [
    'server' => 'ftp.example.com',
    'username' => 'username',
    'password' => 'password',
    'port' => '22',
  ],
);
``` 

Once we have the credentials of the FTP server we use a `downloadFile()` method to download the remote CSV file. Here's an extract of the relevant code:

```php
protected function downloadFile(array $conn_config) {
  ...
  // Prepare to download file to a temporary directory.
  $path_remote = $conn_config['path'];
  $basename = basename($path_remote);
  $path_local = file_directory_temp() . '/' . $basename;
  // Download file by SFTP and place it in temporary directory.
  $sftp = static::getSFTPConnection($conn_config);
  if (!$sftp->get($path_remote, $path_local)) {
    throw new MigrateException('Cannot download remote file ' . $basename . ' by SFTP.');
  }
  // Return path to the local of the file.
  // This will in turn be passed to the parent CSV plugin.
  return $path_local;
}
```

This method creates an SFTP connection, downloads the file to a temporary location and returns the path to the downloaded file. The temporary file path is then passed to the `Migrate Source CSV` and that's it! Finally, to use the plugin in our migration we just set our plugin as the `source/plugin`:

```yaml
id: migrate_example_content
label: 'Example content'
...
source:
  plugin: migrate_example_source_remote_csv
  # Settings for our custom Remote CSV plugin.
  sftp:
    settings: sftp
    path: "/path/to/file/example_content.csv"
  # Settings for the contrib CSV plugin.
  header_row_count: 1
  keys:
    - id
...
```

The code for this plugin and the example module is available at [migrate_example_source](https://github.com/evolvingweb/migrate_example_source). Great!
