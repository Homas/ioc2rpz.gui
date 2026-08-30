<?php
/**
 * Property-based test for the backend IOC indicator length validation in
 * www/io2fun.php (task 12.2): validateIocLength().
 *
 * Feature: ioc-source-attribution, Property 8: Backend indicator length
 * validation is a closed interval
 *
 * Property (design.md, Property 8):
 *   For any submitted indicator string, the Mgmt_Proxy backend SHALL accept the
 *   request (proceed to contact the management interface) if and only if the
 *   indicator length is between 1 and 2048 characters inclusive; an empty or
 *   over-length indicator SHALL be rejected with a validation error and SHALL
 *   NOT contact the management interface.
 *
 * validateIocLength() is the isolated, pure length-validation contract that the
 * `GET ioc_lookup` endpoint consults BEFORE any network step: the endpoint
 * short-circuits (returns the validation-failure JSON and `break`s) when this
 * returns false, so no curl / management-interface contact ever occurs for a
 * rejected input. Testing the boolean therefore exercises exactly the
 * "accept iff length in [1,2048]" AND the "rejected => no mgmt contact"
 * contract: reject == the request never reaches the network step.
 *
 * The full endpoint additionally requires a DB, a session, and curl, so it
 * cannot be invoked directly in a unit test; the pure predicate is the exact
 * decision that gates all of that.
 *
 * Validates: Requirements 8.7, 8.1
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

final class IndicatorLengthValidationPropertyTest extends TestCase
{
    use TestTrait;

    /** Minimum property iterations mandated by the design (>= 100). */
    private const ITERATIONS = 200;

    /** Inclusive lower bound of the accepting interval. */
    private const MIN_LEN = 1;

    /** Inclusive upper bound of the accepting interval. */
    private const MAX_LEN = 2048;

    /**
     * Feature: ioc-source-attribution, Property 8: Backend indicator length
     * validation is a closed interval.
     *
     * For an indicator built to an exact length, validateIocLength() accepts it
     * if and only if that length lies within [1, 2048]. A rejected input (the
     * boolean is false) is precisely the case in which the endpoint returns the
     * validation error and does NOT contact the management interface.
     *
     * Validates: Requirements 8.7, 8.1
     */
    public function testAcceptedIffLengthWithinClosedInterval(): void
    {
        $this->limitTo(self::ITERATIONS);
        $this->forAll($this->lengthGenerator())
            ->then(function (int $len): void {
                // Build an indicator string of exactly $len single-byte chars.
                $ioc = $len === 0 ? '' : str_repeat('a', $len);
                $this->assertSame(
                    $len,
                    strlen($ioc),
                    'Test setup: constructed indicator must have the intended byte length'
                );

                $accepted = validateIocLength($ioc);
                $expected = ($len >= self::MIN_LEN && $len <= self::MAX_LEN);

                $this->assertSame(
                    $expected,
                    $accepted,
                    "length=$len must be " . ($expected ? 'ACCEPTED' : 'REJECTED')
                        . " (closed interval [1,2048])"
                );
            });
    }

    /**
     * The same closed-interval contract, but over indicators with arbitrary
     * (non-uniform, mixed-character) content rather than a repeated single
     * character — so acceptance depends only on the byte length, never on the
     * content. Uses PHP's byte-length semantics (strlen), matching the endpoint.
     *
     * Validates: Requirements 8.7, 8.1
     */
    public function testAcceptanceDependsOnlyOnByteLength(): void
    {
        $this->limitTo(self::ITERATIONS);
        $this->forAll(Generator\string())
            ->then(function (string $ioc): void {
                $len      = strlen($ioc);
                $accepted = validateIocLength($ioc);
                $expected = ($len >= self::MIN_LEN && $len <= self::MAX_LEN);

                $this->assertSame(
                    $expected,
                    $accepted,
                    'byte-length=' . $len . ' of arbitrary content must be '
                        . ($expected ? 'ACCEPTED' : 'REJECTED')
                );
            });
    }

    /**
     * Explicit boundary-edge examples required by the task: lengths 0, 1, 2048,
     * and 2049. These pin the exact edges of the closed interval so a
     * regression at a boundary is caught deterministically (not just
     * probabilistically by the property runs).
     *
     * length 0    -> rejected (empty, no mgmt contact)
     * length 1    -> accepted (lower edge, inclusive)
     * length 2048 -> accepted (upper edge, inclusive)
     * length 2049 -> rejected (over-length, no mgmt contact)
     *
     * Validates: Requirements 8.7, 8.1
     */
    public function testClosedIntervalBoundaryEdges(): void
    {
        $this->assertFalse(validateIocLength(''),                    'length 0 (empty) must be rejected');
        $this->assertTrue(validateIocLength(str_repeat('a', 1)),     'length 1 must be accepted (lower edge)');
        $this->assertTrue(validateIocLength(str_repeat('a', 2048)),  'length 2048 must be accepted (upper edge)');
        $this->assertFalse(validateIocLength(str_repeat('a', 2049)), 'length 2049 must be rejected (over-length)');

        // Absent/null indicator is treated as empty -> rejected (no mgmt contact).
        $this->assertFalse(validateIocLength(null), 'null indicator must be rejected');
    }

    /**
     * Generates candidate indicator lengths.
     *
     * The generator is weighted toward the interval boundaries — it always
     * includes the required edges (0, 1, 2, 2047, 2048, 2049) and a band of
     * lengths immediately around each edge — while also sampling uniformly
     * across the full range (well within, and well beyond the upper bound) so
     * the closed-interval contract is exercised inside, on, and outside the
     * interval.
     *
     * @return Generator<int>
     */
    private function lengthGenerator(): Generator
    {
        $edges = [
            0, 1, 2, 3,                 // lower boundary band
            2046, 2047, 2048, 2049, 2050, // upper boundary band
            1024,                       // well inside
            4096,                       // well outside
        ];

        return Generator\oneOf(
            Generator\elements($edges),
            // A small window just below/at/above each edge, to catch off-by-one.
            Generator\choose(0, 5),
            Generator\choose(2044, 2052),
            // Broad coverage across and beyond the interval.
            Generator\choose(0, 4096)
        );
    }
}
