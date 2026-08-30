<?php
/**
 * Persistence tests for the nullable DNS rate limit columns.
 *
 * The feature adds five nullable columns:
 *   - servers.rl_window, servers.rl_max_requests, servers.rl_max_unknown_requests
 *   - rpzs.rl_window, rpzs.rl_max_requests
 *
 * They are nullable on purpose: NULL means "inherit" and MUST stay distinguishable
 * from a value explicitly set to the same number as the inherited one. The critical
 * edge is 0, which is a legitimate value for the maximums ("refuse every request in
 * that bucket") and must never be conflated with NULL.
 *
 * As with PersistenceTest, the POST/PUT handlers in io2data.php cannot be invoked in
 * isolation (they need session state, request routing and header side effects), so
 * these drive the REAL DB helpers loaded by the bootstrap (DB_execute /
 * DB_selectArray / DB_intOrNull / DB_close) against a temporary SQLite database whose
 * schema carries the v4 columns. The INSERT/UPDATE SQL mirrors the shape emitted by
 * io2data.php's handlers, including its use of DB_intOrNull().
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RateLimitPersistenceTest extends TestCase
{
    /** v4 `servers` schema: v3 columns plus the three nullable rate limit columns. */
    private const SERVERS_COLUMNS =
        'user_id integer, name text, ip text, pub_ip text, ns text, email text, ' .
        'mgmt integer, disabled integer, stype integer, URL text, cfg_updated integer, ' .
        'publish_upd integer, certfile text, keyfile text, cacertfile text, custom_config text, ' .
        "track_default text default 'off', " .
        'rl_window integer default NULL, rl_max_requests integer default NULL, ' .
        'rl_max_unknown_requests integer default NULL';

    /** v4 `rpzs` schema: v3 columns plus the two nullable rate limit columns. */
    private const RPZS_COLUMNS =
        'user_id integer, name text, soa_refresh integer, soa_update_retry integer, ' .
        'soa_expiration integer, soa_nx_ttl integer, cache integer, wildcard integer, ' .
        'action text, ioc_type text, axfr_update integer, ixfr_update integer, disabled integer, ' .
        "track_sources text default 'Inherit', " .
        'rl_window integer default NULL, rl_max_requests integer default NULL';

    private string $dbFile = '';

    protected function setUp(): void
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'io2rl_') ?: '';
        $this->assertNotSame('', $this->dbFile, 'Failed to allocate a temp DB file');
        @unlink($this->dbFile);

        $db = new SQLite3($this->dbFile);
        $db->exec('create table servers (' . self::SERVERS_COLUMNS . ');');
        $db->exec('create table rpzs (' . self::RPZS_COLUMNS . ');');
        $db->close();
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbFile, $this->dbFile . '-wal', $this->dbFile . '-shm'] as $f) {
            if ($f !== '' && is_file($f)) {
                @unlink($f);
            }
        }
    }

    private function open(): SQLite3
    {
        return new SQLite3($this->dbFile);
    }

    /**
     * Inserts one server row, binding the rate limits exactly as io2data.php's POST
     * handler does: through DB_intOrNull(), unquoted, so an empty input becomes the
     * SQL keyword NULL rather than 0 or ''.
     *
     * @param mixed $window
     * @param mixed $maxRequests
     * @param mixed $maxUnknown
     */
    private function insertServer(SQLite3 $db, string $name, $window, $maxRequests, $maxUnknown): void
    {
        $w = DB_intOrNull($window);
        $m = DB_intOrNull($maxRequests);
        $u = DB_intOrNull($maxUnknown);
        $sql = 'insert into servers values(' .
            "1,'" . DB_escape($db, $name) . "','10.0.0.1','203.0.113.1','ns.example','a@example'," .
            "1,0,0,'x.conf',0,0,'','','','','off',$w,$m,$u)";
        $this->assertNotFalse(DB_execute($db, $sql), "INSERT should succeed for ($w,$m,$u)");
    }

    /**
     * @param mixed $window
     * @param mixed $maxRequests
     */
    private function insertRpz(SQLite3 $db, string $name, $window, $maxRequests): void
    {
        $w = DB_intOrNull($window);
        $m = DB_intOrNull($maxRequests);
        $sql = 'insert into rpzs values(' .
            "1,'" . DB_escape($db, $name) . "',86400,3600,2592000,7200,1,1,'nxdomain','mixed'," .
            "604800,86400,0,'Inherit',$w,$m)";
        $this->assertNotFalse(DB_execute($db, $sql), "INSERT should succeed for ($w,$m)");
    }

    /** @return array<string,mixed> */
    private function readRow(SQLite3 $db, string $table, array $cols, int $rowid): array
    {
        return DB_selectArray($db, 'select ' . implode(',', $cols) . " from $table where rowid=$rowid")[0];
    }

    /**
     * DB_intOrNull is the single point where the NULL/0 distinction is made, so pin its
     * contract directly before exercising it through SQL.
     */
    public function testIntOrNullPreservesZeroAndMapsAbsentToNull(): void
    {
        // Absent in every shape the request can deliver it => the SQL keyword NULL.
        $this->assertSame('NULL', DB_intOrNull(null));
        $this->assertSame('NULL', DB_intOrNull(''));
        $this->assertSame('NULL', DB_intOrNull(false));

        // 0 is a real value and must survive as the literal 0, never as NULL.
        $this->assertSame('0', DB_intOrNull(0));
        $this->assertSame('0', DB_intOrNull('0'));

        // Ordinary values.
        $this->assertSame('60', DB_intOrNull(60));
        $this->assertSame('60', DB_intOrNull('60'));
        $this->assertSame('60', DB_intOrNull(' 60 '));

        // Anything non-integer degrades to NULL rather than to a partial number, so a
        // malformed or hostile value can never reach the SQL statement.
        foreach (['abc', '6.5', '1e3', '60; drop table servers', "1'--", [], 'NULL'] as $bad) {
            $this->assertSame('NULL', DB_intOrNull($bad), 'non-integer input must yield NULL');
        }
    }

    /**
     * NULL round-trips as inherit: an absent input stores NULL and reads back as null,
     * for every one of the five columns.
     */
    public function testNullRoundTripsAsInheritOnBothTables(): void
    {
        $db = $this->open();

        $this->insertServer($db, 'srv_inherit', '', '', '');
        $srvId = (int) $db->lastInsertRowID();
        $srv = $this->readRow($db, 'servers',
            ['rl_window', 'rl_max_requests', 'rl_max_unknown_requests'], $srvId);
        $this->assertNull($srv['rl_window']);
        $this->assertNull($srv['rl_max_requests']);
        $this->assertNull($srv['rl_max_unknown_requests']);

        $this->insertRpz($db, 'feed_inherit', '', '');
        $rpzId = (int) $db->lastInsertRowID();
        $rpz = $this->readRow($db, 'rpzs', ['rl_window', 'rl_max_requests'], $rpzId);
        $this->assertNull($rpz['rl_window']);
        $this->assertNull($rpz['rl_max_requests']);

        DB_close($db);
    }

    /**
     * An explicit 0 round-trips as 0 and stays distinguishable from NULL. This is the
     * distinction the nullable columns exist for.
     */
    public function testZeroRoundTripsAsZeroAndIsDistinctFromNull(): void
    {
        $db = $this->open();

        $this->insertServer($db, 'srv_zero', 60, 0, 0);
        $zeroId = (int) $db->lastInsertRowID();
        $zero = $this->readRow($db, 'servers',
            ['rl_window', 'rl_max_requests', 'rl_max_unknown_requests'], $zeroId);
        $this->assertSame(60, $zero['rl_window']);
        $this->assertSame(0, $zero['rl_max_requests']);
        $this->assertSame(0, $zero['rl_max_unknown_requests']);
        $this->assertNotNull($zero['rl_max_requests'], '0 must not be stored as NULL');

        $this->insertRpz($db, 'feed_zero', 30, 0);
        $rpzId = (int) $db->lastInsertRowID();
        $rpz = $this->readRow($db, 'rpzs', ['rl_window', 'rl_max_requests'], $rpzId);
        $this->assertSame(0, $rpz['rl_max_requests']);
        $this->assertNotNull($rpz['rl_max_requests']);

        // A NULL row and a 0 row are queryably different.
        $nulls = DB_selectArray($db, 'select rowid from rpzs where rl_max_requests is null');
        $zeros = DB_selectArray($db, 'select rowid from rpzs where rl_max_requests = 0');
        $this->assertCount(0, $nulls, 'the 0 row must not match "is null"');
        $this->assertCount(1, $zeros);

        DB_close($db);
    }

    /**
     * Every representative value round-trips exactly through both the INSERT (POST)
     * and UPDATE (PUT) paths, including one option set while the other stays NULL.
     */
    public function testEachValueRoundTripsThroughInsertAndUpdate(): void
    {
        $db = $this->open();

        $cases = [
            ['', '', ''],        // nothing set
            [60, '', ''],        // window only
            ['', 20, ''],        // max_requests only
            ['', '', 1],         // max_unknown_requests only
            [60, 20, 1],         // all three
            [1, 0, 0],           // range edges
        ];

        foreach ($cases as $i => [$w, $m, $u]) {
            $this->insertServer($db, "srv_$i", $w, $m, $u);
            $rowid = (int) $db->lastInsertRowID();
            $row = $this->readRow($db, 'servers',
                ['rl_window', 'rl_max_requests', 'rl_max_unknown_requests'], $rowid);
            $this->assertSame($w === '' ? null : $w, $row['rl_window'], "window case $i");
            $this->assertSame($m === '' ? null : $m, $row['rl_max_requests'], "max case $i");
            $this->assertSame($u === '' ? null : $u, $row['rl_max_unknown_requests'], "unknown case $i");
        }

        // UPDATE (PUT) path: edit one record through each case in turn, mirroring the
        // `set rl_window=$x, ...` shape io2data.php emits.
        $this->insertServer($db, 'srv_edit', '', '', '');
        $editId = (int) $db->lastInsertRowID();
        foreach ($cases as $i => [$w, $m, $u]) {
            $sql = 'update servers set rl_window=' . DB_intOrNull($w) .
                ', rl_max_requests=' . DB_intOrNull($m) .
                ', rl_max_unknown_requests=' . DB_intOrNull($u) .
                " where rowid=$editId";
            $this->assertNotFalse(DB_execute($db, $sql), "UPDATE should succeed for case $i");
            $row = $this->readRow($db, 'servers',
                ['rl_window', 'rl_max_requests', 'rl_max_unknown_requests'], $editId);
            $this->assertSame($w === '' ? null : $w, $row['rl_window'], "update window case $i");
            $this->assertSame($m === '' ? null : $m, $row['rl_max_requests'], "update max case $i");
            $this->assertSame($u === '' ? null : $u, $row['rl_max_unknown_requests'], "update unknown case $i");
        }

        DB_close($db);
    }

    /**
     * Clearing a previously set value back to empty stores NULL again, i.e. an operator
     * can genuinely return a field to "inherit" rather than being stuck with a number.
     */
    public function testClearingAStoredValueRestoresInherit(): void
    {
        $db = $this->open();

        $this->insertRpz($db, 'feed_clear', 30, 20);
        $rowid = (int) $db->lastInsertRowID();
        $this->assertSame(30, $this->readRow($db, 'rpzs', ['rl_window'], $rowid)['rl_window']);

        $sql = 'update rpzs set rl_window=' . DB_intOrNull('') .
            ', rl_max_requests=' . DB_intOrNull('') . " where rowid=$rowid";
        $this->assertNotFalse(DB_execute($db, $sql));

        $row = $this->readRow($db, 'rpzs', ['rl_window', 'rl_max_requests'], $rowid);
        $this->assertNull($row['rl_window'], 'cleared field returns to inherit');
        $this->assertNull($row['rl_max_requests']);

        DB_close($db);
    }

    /**
     * A failed storage write leaves the previously persisted rate limits unchanged
     * (mirrors PersistenceTest's forced-failure cases for the attribution columns).
     */
    public function testForcedStorageFailureLeavesPriorValuesIntact(): void
    {
        $db = $this->open();

        $this->insertRpz($db, 'feed_fail', 30, 20);
        $rowid = (int) $db->lastInsertRowID();

        $badSql = "update rpzs set rl_window=99, no_such_column='x' where rowid=$rowid";
        $this->assertFalse(@DB_execute($db, $badSql), 'A failing storage write returns false');

        $row = $this->readRow($db, 'rpzs', ['rl_window', 'rl_max_requests'], $rowid);
        $this->assertSame(30, $row['rl_window'], 'prior value must survive a failed write');
        $this->assertSame(20, $row['rl_max_requests']);

        DB_close($db);
    }
}
