<?php
/**
 * Placeholder smoke test that verifies the PHP property/unit test stack
 * (PHPUnit + Eris) is wired up and that the pure functions from
 * www/io2vars.php and www/io2fun.php are loadable via the bootstrap.
 *
 * Feature-specific property tests (Properties 1, 2, 5, 8) are added by later
 * tasks.
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

final class SetupSmokeTest extends TestCase
{
    use TestTrait;

    public function testRunsPhpunit(): void
    {
        $this->assertTrue(true);
    }

    public function testRunsErisPropertyTests(): void
    {
        $this->forAll(Generator\int())
            ->then(function (int $n): void {
                $this->assertSame($n, $n + 0);
            });
    }

    public function testBackendPureFunctionsResolve(): void
    {
        // A representative pure helper from each backend file loaded by the
        // bootstrap. Property/unit tests in later tasks exercise the new
        // attribution helpers/validators added to these same files.
        $this->assertTrue(function_exists('erlEscape'), 'io2vars.php should be loaded');
        $this->assertTrue(function_exists('getProto'), 'io2fun.php should be loaded');
    }
}
