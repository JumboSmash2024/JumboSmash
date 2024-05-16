<?php

/**
 * Set up everything that we need, should be included in every entry point.
 */

/**
 * Config - display all errors, ensure using UTC, and ignore user aborts when
 * things might affect the database
 */
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
ini_set( 'date.timezone', 'UTC' );

if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' ) {
    ignore_user_abort( true );
}

/**
 * Require `mysqli`
 */
if ( !extension_loaded( 'mysqli' ) ) {
    trigger_error( 'Required extension `mysqli`is missing!', E_USER_ERROR );
}


// Session
session_start();

// Composer autoloader
require_once( 'vendor/autoload.php' );

// LOAD ALL FILES in the following directories:
$includeFiles = array_merge(
    glob( 'src/*.php' ),
    glob( 'src/*/*.php' ),
);
// var_export( $includeFiles );
foreach ( $includeFiles as $file ) {
    require_once $file;
}
// Avoid globals
unset( $file );
unset( $includeFiles );


/**
 * DATABASE SETUP
 */
define( 'JUMBO_SMASH_DB_HOST', 'db' );
define( 'JUMBO_SMASH_DB_USER', 'root' );
define( 'JUMBO_SMASH_DB_PASS', 'root' );
define( 'JUMBO_SMASH_DB_NAME', 'jumbo_smash_db' );

// Individual classes have extra setup
$extraSetup = [
    \JumboSmash\Services\Database::class,
    \JumboSmash\Services\Management::class, // uses the database
];
foreach ( $extraSetup as $setup ) {
    $setup::doSetup();
}
unset( $extraSetup );
unset( $setup );