<?php
/**
 * PHPUnit bootstrap for the ioc2rpz.gui PHP test stack.
 *
 * Responsibilities:
 *  - Load the Composer autoloader so PHPUnit and Eris (property-based testing)
 *    are available.
 *  - Require the backend files that hold the pure functions under test:
 *      - www/io2vars.php  -> Config_Writer helpers (erlSrvTrackSources, etc.)
 *      - www/io2fun.php   -> validators (validateServerFields, etc.)
 *
 * Both files only define constants and functions at include time (no DB
 * connection, session, or header side effects are triggered on require), so
 * they are safe to load once here and reuse across every test case.
 *
 * Feature-specific property tests (Properties 1, 2, 5, 8) are added by later
 * tasks; this bootstrap just wires the stack together.
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

$autoload = __DIR__ . '/../../vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(
        STDERR,
        "Composer dependencies are not installed.\n" .
        "Run `composer install` from the project root to install PHPUnit and Eris\n" .
        "before running the PHP test suite.\n"
    );
    exit(1);
}

require_once $autoload;

// Backend under test. Paths are resolved relative to this bootstrap file so the
// suite can be run from any working directory.
$wwwDir = realpath(__DIR__ . '/../../www');

require_once $wwwDir . '/io2vars.php';
require_once $wwwDir . '/io2fun.php';
