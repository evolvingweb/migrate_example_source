<?php
/**
 * @file
 * Example settings.local.php file.
 */

// This is how I configured SFTP credentials in my settings.local.php.
$settings['sftp'] = array(
  'default' => [
    'server' => 'sftp.example.com',
    'username' => 'user',
    'password' => 'password',
    'port' => 22,
  ],
);
