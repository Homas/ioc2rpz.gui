<?php
/**
 * Migration example tests for the IOC source-attribution schema upgrade
 * (Task 1.4). These exercise the version 2 -> 3 migration defined in
 * scripts/upgrade_db.php (DBVersion=3, fall-through `case 2:` that adds
 * servers.track_default and rpzs.track_sources) against a temporary SQLite
 * database seeded at user_version=2.
 *
 * Why the migration SQL is re-created here instead of `require`-ing
 * scripts/upgrade_db.php directly:
 *   - upgrade_db.php hard-codes `define("IO2PATH", "/opt/ioc2rpz.gui")` and
 *     `require IO2PATH."/www/io2vars.php"`, a path that does not exist in the
 *     test environment.
 *   - It invokes `upgradeSQLiteDB(IO2PATH."/www/".DBFile)` at file scope, i.e.
 *     it would run the migration against the production database on include.
 * So the script cannot be loaded in isolation. Instead, runMigration() below
 * mirrors upgradeSQLiteDB() statement-for-statement (same fall-through switch,
 * same guard `if ($db_version != target)`, same additive SQL) and drives it
 * through the *real* DB helper functions (DB_selectArray / DB_execute /
 * DB_close) that the bootstrap loads from www/io2vars.php. This keeps the test
 * exercising the actual migration SQL and guard logic.
 *
 * Covers Requirements:
 *   11.3 - existing servers default to `off`
 *   11.4 - all pre-upgrade field values remain unchanged
 *   11.5 - existing feeds default to `Inherit`
 *   11.6 - a failed upgrade leaves user_version and data intact
 *   11.7 - re-running against an already-upgraded DB is a no-op
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MigrationTest extends TestCase
{
    /**
     * Target schema version of the attribution migration (matches
     * DBVersion in scripts/upgrade_db.php).
     */
    private const TARGET_VERSION = 3;

    /**
     * Pre-migration (version 2) `servers` table column definition, i.e. the
     * schema *without* the new `track_default` column. Mirrors init_db.php's
     * servers table minus the attribution column added by task 1.2.
     */
    private const SERVERS_V2_COLUMNS =
        'user_id integer, name text, ip text, pub_ip text, ns text, email text, ' .
        'mgmt integer, disabled integer, stype integer, URL text, cfg_updated integer, ' .
        'publish_upd integer, certfile text, keyfile text, cacertfile text, custom_config text';

    /**
     * Pre-migration (version 2) `rpzs` table column definition, i.e. the schema
     * *without* the new `track_sources` column.
     */
    private const RPZS_V2_COLUMNS =
        'user_id integer, name text, soa_refresh integer, soa_update_retry integer, ' .
        'soa_expiration integer, soa_nx_ttl integer, cache integer, wildcard integer, ' .
        'action text, ioc_type text, axfr_update integer, ixfr_update integer, disabled integer';

    /**
     * Absolute path to the temporary SQLite database used by the current test.
     *
     * @var string
     */
    private string $dbFile = '';

    protected function setUp(): void
    {
        // A unique temp file per test; created empty then populated by seedV2Database().
        $this->dbFile = tempnam(sys_get_temp_dir(), 'io2mig_') ?: '';
        $this->assertNotSame('', $this->dbFile, 'Failed to allocate a temp DB file');
        // Remove the empty placeholder so SQLite3 creates a fresh database.
        @unlink($this->dbFile);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbFile, $this->dbFile . '-wal', $this->dbFile . '-shm'] as $f) {
            if ($f !== '' && is_file($f)) {
                @unlink($f);
            }
        }
    }

    /**
     * Reproduces scripts/upgrade_db.php::upgradeSQLiteDB() for the version 2->3
     * migration, using the real DB helper functions loaded by the bootstrap.
     *
     * @param string $dbFile Path to the SQLite database to migrate.
     * @return array{db_version:int, executed:bool, result:mixed} Migration outcome:
     *         - db_version: the user_version read *before* migrating
     *         - executed:   whether the guarded batch ran (version != target)
     *         - result:     DB_execute() return value (false on SQL failure), or
     *                       null when the guard skipped execution
     */
    private function runMigration(string $dbFile): array
    {
        $db = new SQLite3($dbFile);
        $db_version = (int) (DB_selectArray($db, 'PRAGMA user_version')[0]['user_version']);

        $sql = '';
        switch ($db_version) {
            case 0:
                // (earlier migrations elided - not relevant to a v2 seed)
            case 1:
                // (earlier migrations elided - not relevant to a v2 seed)
            case 2:
                $sql .= "alter table servers add column track_default text default 'off';";
                $sql .= "alter table rpzs add column track_sources text default 'Inherit';";
            default:
                $sql .= 'PRAGMA user_version=' . self::TARGET_VERSION . ';';
        }

        $executed = false;
        $result   = null;
        if ($db_version !== self::TARGET_VERSION) {
            $executed = true;
            $result   = DB_execute($db, $sql);
        }

        DB_close($db);

        return ['db_version' => $db_version, 'executed' => $executed, 'result' => $result];
    }

    /**
     * Creates a version-2 database with varied server/feed rows.
     *
     * @param bool $preAddTrackDefault When true, the `servers` table is created
     *        WITH a `track_default` column already present so the migration's
     *        `ALTER TABLE servers ADD COLUMN track_default` fails - used to force
     *        a migration failure for the Req 11.6 test.
     */
    private function seedV2Database(bool $preAddTrackDefault = false): void
    {
        $db = new SQLite3($this->dbFile);

        $serversColumns = self::SERVERS_V2_COLUMNS;
        if ($preAddTrackDefault) {
            $serversColumns .= ", track_default text default 'off'";
        }

        $db->exec('PRAGMA user_version=2;');
        $db->exec("create table servers ($serversColumns);");
        $db->exec('create table rpzs (' . self::RPZS_V2_COLUMNS . ');');

        // Varied server rows (different values across every column).
        $serverValues = $preAddTrackDefault
            ? "(1,'server_a','10.0.0.1','203.0.113.1','ns1.example','a@example',1,0,0,'a.conf',1,1,'a.pem','a.key','','cfgA','off')," .
              "(2,'server_b','10.0.0.2','203.0.113.2','ns2.example','b@example',0,1,2,'b.conf',0,0,'','','ca.pem','',' off')"
            : "(1,'server_a','10.0.0.1','203.0.113.1','ns1.example','a@example',1,0,0,'a.conf',1,1,'a.pem','a.key','','cfgA')," .
              "(2,'server_b','10.0.0.2','203.0.113.2','ns2.example','b@example',0,1,2,'b.conf',0,0,'','','ca.pem','')";
        $db->exec("insert into servers values $serverValues;");

        // Varied feed rows.
        $db->exec(
            "insert into rpzs values " .
            "(1,'feed.a',86400,3600,2592000,7200,1,1,'nxdomain','mixed',604800,86400,0)," .
            "(2,'feed.b',3600,600,604800,300,0,0,'drop','ip',3600,900,1);"
        );

        $db->close();
    }

    /**
     * Reads every row of a table ordered by rowid, restricted to the given columns.
     *
     * @param string[] $columns
     * @return array<int,array<string,mixed>>
     */
    private function readRows(string $table, array $columns): array
    {
        $db   = new SQLite3($this->dbFile);
        $cols = implode(',', $columns);
        $rows = DB_selectArray($db, "select $cols from $table order by rowid");
        $db->close();

        return $rows;
    }

    private function readUserVersion(): int
    {
        $db  = new SQLite3($this->dbFile);
        $ver = (int) (DB_selectArray($db, 'PRAGMA user_version')[0]['user_version']);
        $db->close();

        return $ver;
    }

    /**
     * @return string[] Column names currently present on the table.
     */
    private function tableColumns(string $table): array
    {
        $db   = new SQLite3($this->dbFile);
        $info = DB_selectArray($db, "PRAGMA table_info($table)");
        $db->close();

        return array_column($info, 'name');
    }

    /**
     * Req 11.3, 11.4, 11.5: after upgrading a v2 DB, every existing server
     * defaults track_default to `off`, every existing feed defaults
     * track_sources to `Inherit`, and all pre-upgrade field values are unchanged.
     */
    public function testMigrationBackfillsDefaultsAndPreservesExistingData(): void
    {
        $this->seedV2Database();

        // Capture the pre-upgrade field values (original v2 columns only).
        $serversBefore = $this->readRows('servers', ['rowid', 'name', 'ip', 'email', 'custom_config', 'disabled']);
        $rpzsBefore    = $this->readRows('rpzs', ['rowid', 'name', 'action', 'cache', 'disabled']);

        $outcome = $this->runMigration($this->dbFile);

        $this->assertTrue($outcome['executed'], 'Migration should run when version != target');
        $this->assertNotFalse($outcome['result'], 'Migration batch should succeed');
        $this->assertSame(self::TARGET_VERSION, $this->readUserVersion(), 'user_version should advance to 3');

        // Req 11.3 / 11.5: new columns exist and every existing row got the default.
        $servers = $this->readRows('servers', ['rowid', 'track_default']);
        foreach ($servers as $row) {
            $this->assertSame('off', $row['track_default'], 'Existing servers default to off');
        }

        $rpzs = $this->readRows('rpzs', ['rowid', 'track_sources']);
        foreach ($rpzs as $row) {
            $this->assertSame('Inherit', $row['track_sources'], 'Existing feeds default to Inherit');
        }

        // Req 11.4: all pre-upgrade field values remain unchanged.
        $serversAfter = $this->readRows('servers', ['rowid', 'name', 'ip', 'email', 'custom_config', 'disabled']);
        $rpzsAfter    = $this->readRows('rpzs', ['rowid', 'name', 'action', 'cache', 'disabled']);
        $this->assertEquals($serversBefore, $serversAfter, 'Existing server field values must be unchanged');
        $this->assertEquals($rpzsBefore, $rpzsAfter, 'Existing feed field values must be unchanged');
    }

    /**
     * Req 11.7: re-running the migration against an already-upgraded DB is a
     * no-op that preserves configured attribution settings and all other data.
     */
    public function testSecondRunIsNoOpPreservingSettings(): void
    {
        $this->seedV2Database();
        $this->runMigration($this->dbFile);

        // Simulate an operator configuring non-default attribution after the upgrade.
        $db = new SQLite3($this->dbFile);
        $db->exec("update servers set track_default='auto' where rowid=1;");
        $db->exec("update rpzs set track_sources='true' where rowid=2;");
        $db->close();

        $serversBefore = $this->readRows('servers', ['rowid', 'name', 'track_default']);
        $rpzsBefore    = $this->readRows('rpzs', ['rowid', 'name', 'track_sources']);

        // Second run: guard should skip execution because version already == target.
        $outcome = $this->runMigration($this->dbFile);
        $this->assertFalse($outcome['executed'], 'Second run must not execute the migration batch');
        $this->assertSame(self::TARGET_VERSION, $this->readUserVersion(), 'Version stays at 3');

        // Settings and data preserved exactly.
        $this->assertEquals($serversBefore, $this->readRows('servers', ['rowid', 'name', 'track_default']));
        $this->assertEquals($rpzsBefore, $this->readRows('rpzs', ['rowid', 'name', 'track_sources']));
        $this->assertSame('auto', $this->readRows('servers', ['track_default'])[0]['track_default']);
        $this->assertSame('true', $this->readRows('rpzs', ['track_sources'])[1]['track_sources']);
    }

    /**
     * Req 11.6: if the upgrade fails, user_version is not advanced and existing
     * data is left intact (the additive ADD COLUMN statements make no partial
     * destructive change).
     *
     * The failure is forced deterministically by seeding a v2 DB whose `servers`
     * table already has a `track_default` column, so the migration's first
     * statement (`ALTER TABLE servers ADD COLUMN track_default`) fails with a
     * duplicate-column error and the batch aborts before the user_version PRAGMA.
     */
    public function testFailedMigrationLeavesVersionAndDataIntact(): void
    {
        $this->seedV2Database(true);

        $serversBefore = $this->readRows('servers', ['rowid', 'name', 'ip', 'email', 'custom_config', 'track_default']);
        $rpzsBefore    = $this->readRows('rpzs', ['rowid', 'name', 'action', 'cache', 'disabled']);

        $outcome = $this->runMigration($this->dbFile);

        // The guarded batch ran but the SQL failed -> error indication returned.
        $this->assertTrue($outcome['executed'], 'Migration batch runs while version=2');
        $this->assertFalse($outcome['result'], 'A failing upgrade returns an error indication (false)');

        // Req 11.6: user_version is NOT advanced.
        $this->assertSame(2, $this->readUserVersion(), 'Failed upgrade must not advance user_version');

        // The batch aborted on the first statement, so rpzs never gained track_sources.
        $this->assertNotContains('track_sources', $this->tableColumns('rpzs'), 'No partial schema change on failure');

        // Req 11.6: existing row data is unchanged.
        $serversAfter = $this->readRows('servers', ['rowid', 'name', 'ip', 'email', 'custom_config', 'track_default']);
        $rpzsAfter    = $this->readRows('rpzs', ['rowid', 'name', 'action', 'cache', 'disabled']);
        $this->assertEquals($serversBefore, $serversAfter, 'Server data intact after failed upgrade');
        $this->assertEquals($rpzsBefore, $rpzsAfter, 'Feed data intact after failed upgrade');
    }
}
