<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*************************************************************************************/

/*
 * Bootstrap for the module's own unit tests: no kernel, no database.
 *
 * Three things have to be arranged by hand, and each of them is a consequence of how a
 * Thelia module is loaded at runtime rather than a choice made here.
 */

$moduleDir = \dirname(__DIR__);

/*
 * 1. The Composer autoloader of the Thelia install this module sits in. Point
 *    THELIA_VENDOR_AUTOLOAD at it to run the tests from a checkout that has no install
 *    above it; otherwise it is looked up in the parent directories, which covers both
 *    local/modules/Comment and vendor/thelia/modules/Comment.
 */
$autoload = getenv('THELIA_VENDOR_AUTOLOAD') ?: null;

if (null === $autoload || '' === $autoload) {
    $candidate = $moduleDir;

    while ('/' !== $candidate && \strlen($candidate) > 1) {
        if (is_file($candidate.'/vendor/autoload.php')) {
            $autoload = $candidate.'/vendor/autoload.php';
            break;
        }

        $candidate = \dirname($candidate);
    }
}

if (null === $autoload || !is_file($autoload)) {
    fwrite(
        \STDERR,
        "Cannot find the Thelia Composer autoloader.\n"
        ."Run the tests from inside a Thelia install, or set THELIA_VENDOR_AUTOLOAD to its vendor/autoload.php.\n"
    );

    exit(1);
}

require $autoload;

/*
 * 2. The module's own classes are not in the Composer autoload map: at runtime the kernel
 *    registers them when it activates the module. Nothing does that here.
 */
spl_autoload_register(static function (string $class) use ($moduleDir): void {
    if (!str_starts_with($class, 'Comment\\')) {
        return;
    }

    $file = $moduleDir.'/'.str_replace('\\', '/', substr($class, \strlen('Comment\\'))).'.php';

    if (is_file($file)) {
        require $file;
    }
});

/*
 * 3. Comment\Model\Comment extends a Propel base class that only exists once Propel has
 *    built the model tree into var/propel/<env>/model. These tests are meant to run without
 *    a built install, so the base class comes from a stand-in that carries the columns of
 *    Config/schema.xml and records saves instead of writing them.
 *
 *    It is required before anything can trigger the autoloader above, so the stand-in always
 *    wins over a generated tree that may or may not be in the include path.
 */
require __DIR__.'/Double/PropelBase/Comment.php';
