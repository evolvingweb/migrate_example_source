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

Given a custom module lets call it `migrate_example_source` we need to create a php class in the following location:

```php
/src/Plugin/migrate/source/
```              

Let's call it `MigrateExampleSourceRemoteCSV.php` and will contain this basic code:

```php
/**
 * @MigrateSource(
 *   id = "migrate_example_source_remote_csv"
 * )
 */
class MigrateExampleSourceRemoteCSV extends SourceCSV {
}
```


