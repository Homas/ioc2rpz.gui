<?php
/**
 * Persistence example tests for the IOC source-attribution columns (Task 4.3).
 *
 * These exercise the durable storage contract for the two new scalar columns
 * added by the feature:
 *   - servers.track_default  (values: off, auto, on)          - Req 11.1
 *   - rpzs.track_sources      (values: Inherit, auto, true, false) - Req 11.2
 *
 * The full POST/PUT handlers in io2data.php cannot be invoked in isolation
 * (they require session state, request routing, and header side effects), so
 * these tests drive the *real* DB helper functions (DB_execute /
 * DB_selectArray / DB_escape / DB_close) that the bootstrap loads from
 * www/io2vars.php against a temporary SQLite database whose servers/rpzs tables
 * carry the v3 attribution columns. The INSERT (POST) and UPDATE (PUT) SQL
 * mirrors the shape emitted by io2data.php's handlers.
 *
 * Covers Requirements:
 *   11.1 - Global_Track_Default stored per-server as one of off/auto/on
 *   11.2 - Feed_Track_Setting stored per-feed as one of Inherit/auto/true/false
 *    2.5 - a storage failure leaves any previously persisted value unchanged
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PersistenceTest extends TestCase
{
    /**
     * v3 `servers` schema: the pre-feature columns plus the trailing
     * track_default column added by the migration (task 1.1) / init_db (1.2).
     */
    private const SERVERS_COLUMNS =
        'user_id integer, name text, ip text, pub_ip text, ns text, email text, ' .
        'mgmt integer, disabled integer, stype integer, URL text, cfg_updated integer, ' .
        'publish_upd integer, certfile text, keyfile text, cacertfile text, custom_config text, ' .
        "track_default text default 'off'";

    /**
     * v3 `rpzs` schema: the pre-feature columns plus the trailing track_sources
     * column.
     */
    private const RPZS_COLUMNS =
        'user_id integer, name text, soa_refresh integer, soa_update_retry integer, ' .
        'soa_expiration integer, soa_nx_ttl integer, cache integer, wildcard integer, ' .
        'action text, ioc_type text, axfr_update integer, ixfr_update integer, disabled integer, ' .
        "track_sources text default 'Inherit'";

    /** Valid persisted values for servers.track_default (Req 11.1). */
    private const SERVER_TRACK_DEFAULTS = ['off', 'auto', 'on'];

    /** Valid persisted values for rpzs.track_sources (Req 11.2). */
    private const RPZ_TRACK_SOURCES = ['Inherit', 'auto', 'true', 'false'];

    private string $dbFile = '';

    protected function setUp(): void
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'io2persist_') ?: '';
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

    private function readServerTrackDefault(SQLite3 $db, int $rowid): ?string
    {
        $rows = DB_selectArray($db, "select track_default from servers where rowid=$rowid");
        return $rows[0]['track_default'] ?? null;
    }

    private function readRpzTrackSources(SQLite3 $db, int $rowid): ?string
    {
        $rows = DB_selectArray($db, "select track_sources from rpzs where rowid=$rowid");
        return $rows[0]['track_sources'] ?? null;
    }

    /**
     * Inserts one server row carrying $trackDefault, mirroring the positional
     * `insert into servers values(...)` used by io2data.php's POST handler.
     */
    private function insertServer(SQLite3 $db, string $name, string $trackDefault): void
    {
        $sql = "insert into servers values(" .
            "1,'" . DB_escape($db, $name) . "','10.0.0.1','203.0.113.1','ns.example','a@example'," .
            "1,0,0,'x.conf',0,0,'','','',''," .
            "'" . DB_escape($db, $trackDefault) . "')";
        $this->assertNotFalse(DB_execute($db, $sql), "INSERT should succeed for track_default=$trackDefault");
    }

    /**
     * Inserts one feed row carrying $trackSources, mirroring the positional
     * `insert into rpzs values(...)` used by io2data.php's POST handler.
     */
    private function insertRpz(SQLite3 $db, string $name, string $trackSources): void
    {
        $sql = "insert into rpzs values(" .
            "1,'" . DB_escape($db, $name) . "',86400,3600,2592000,7200,1,1,'nxdomain','mixed',604800,86400,0," .
            "'" . DB_escape($db, $trackSources) . "')";
        $this->assertNotFalse(DB_execute($db, $sql), "INSERT should succeed for track_sources=$trackSources");
    }

    /**
     * Req 11.1: every valid Global_Track_Default value stored via the POST
     * (INSERT) path reads back exactly, and via the PUT (UPDATE) path reads back
     * exactly when a stored record is edited to a different valid value.
     */
    public function testServerTrackDefaultStoresAndReadsBackEachValidValue(): void
    {
        $db = $this->open();

        // INSERT (POST) path: one row per valid value, each read back verbatim.
        foreach (self::SERVER_TRACK_DEFAULTS as $i => $value) {
            $this->insertServer($db, "srv_$i", $value);
            $rowid = (int) $db->lastInsertRowID();
            $this->assertSame(
                $value,
                $this->readServerTrackDefault($db, $rowid),
                "INSERT round-trip for track_default=$value"
            );
        }

        // UPDATE (PUT) path: edit a single record to each valid value in turn.
        $this->insertServer($db, 'srv_edit', 'off');
        $editId = (int) $db->lastInsertRowID();
        foreach (self::SERVER_TRACK_DEFAULTS as $value) {
            $sql = "update servers set track_default='" . DB_escape($db, $value) . "' where rowid=$editId";
            $this->assertNotFalse(DB_execute($db, $sql), "UPDATE should succeed for track_default=$value");
            $this->assertSame(
                $value,
                $this->readServerTrackDefault($db, $editId),
                "UPDATE round-trip for track_default=$value"
            );
        }

        DB_close($db);
    }

    /**
     * Req 11.2: every valid Feed_Track_Setting value stored via the POST
     * (INSERT) path reads back exactly, and via the PUT (UPDATE) path reads back
     * exactly when a stored record is edited to a different valid value.
     */
    public function testRpzTrackSourcesStoresAndReadsBackEachValidValue(): void
    {
        $db = $this->open();

        foreach (self::RPZ_TRACK_SOURCES as $i => $value) {
            $this->insertRpz($db, "feed_$i", $value);
            $rowid = (int) $db->lastInsertRowID();
            $this->assertSame(
                $value,
                $this->readRpzTrackSources($db, $rowid),
                "INSERT round-trip for track_sources=$value"
            );
        }

        $this->insertRpz($db, 'feed_edit', 'Inherit');
        $editId = (int) $db->lastInsertRowID();
        foreach (self::RPZ_TRACK_SOURCES as $value) {
            $sql = "update rpzs set track_sources='" . DB_escape($db, $value) . "' where rowid=$editId";
            $this->assertNotFalse(DB_execute($db, $sql), "UPDATE should succeed for track_sources=$value");
            $this->assertSame(
                $value,
                $this->readRpzTrackSources($db, $editId),
                "UPDATE round-trip for track_sources=$value"
            );
        }

        DB_close($db);
    }

    /**
     * Req 2.5: when a valid Feed_Track_Setting value cannot be persisted because
     * the storage write fails, the previously persisted value is left unchanged.
     *
     * The failure is forced deterministically by issuing an UPDATE that
     * references a non-existent column, so SQLite rejects the statement and
     * DB_execute() returns false without mutating the row. This models
     * io2data.php returning {"status":"failed"} on a DB_execute failure while
     * the prior stored value stands.
     */
    public function testForcedStorageFailureLeavesPriorFeedValueIntact(): void
    {
        $db = $this->open();

        // Establish a known prior value.
        $this->insertRpz($db, 'feed_fail', 'true');
        $rowid = (int) $db->lastInsertRowID();
        $this->assertSame('true', $this->readRpzTrackSources($db, $rowid), 'Prior value established');

        // Force a storage failure: the UPDATE targets a column that does not exist.
        $badSql = "update rpzs set track_sources='auto', no_such_column='x' where rowid=$rowid";
        $this->assertFalse(@DB_execute($db, $badSql), 'A failing storage write returns false');

        // Req 2.5: the previously persisted value is unchanged.
        $this->assertSame(
            'true',
            $this->readRpzTrackSources($db, $rowid),
            'Prior feed track value must survive a failed write'
        );

        DB_close($db);
    }

    /**
     * Req 2.5 (server analogue): a forced storage failure leaves the previously
     * persisted Global_Track_Default value unchanged.
     */
    public function testForcedStorageFailureLeavesPriorServerValueIntact(): void
    {
        $db = $this->open();

        $this->insertServer($db, 'srv_fail', 'auto');
        $rowid = (int) $db->lastInsertRowID();
        $this->assertSame('auto', $this->readServerTrackDefault($db, $rowid), 'Prior value established');

        $badSql = "update servers set track_default='on', no_such_column='x' where rowid=$rowid";
        $this->assertFalse(@DB_execute($db, $badSql), 'A failing storage write returns false');

        $this->assertSame(
            'auto',
            $this->readServerTrackDefault($db, $rowid),
            'Prior server track value must survive a failed write'
        );

        DB_close($db);
    }
}
