<?php
/**
 * Postgresql database config
 */
$config['pgsql']['enable'] = true;
$config['pgsql']['active'] = 'test';
$config['pgsql']['test']['host'] = 'localhost';
$config['pgsql']['test']['port'] = '5432';
$config['pgsql']['test']['user'] = 'postgres';
$config['pgsql']['test']['password'] = '123456';
$config['pgsql']['test']['database'] = 'sd_test';
$config['pgsql']['test']['charset'] = 'utf8';
$config['pgsql']['asyn_max_count'] = 10;

return $config;