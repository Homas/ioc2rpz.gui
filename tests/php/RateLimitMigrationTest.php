<?php
/**
 * Migration tests for the DNS rate limit schema upgrade (DBVersion 3 -> 4).
 *
 * Exercises the `case 3:` block added to scripts/upgrade_db.php, which appends five
 * nullable columns (servers.rl_window / rl_max_requests / rl_max_unknown_requests and
 * rpzs.rl_window / rl_max_requests).
 *
 * The columns are added WITHOUT a numeric default, so existing rows get NULL =
 * inherit and their generated configuration is byte-identical to before the upgrade.
 * Backfilling a number instead would silently convert every existing record from
 * "inherit" to "explicitly set".
 *
 * Why the migration SQL is re-created here rather than `require`-ing
 * scripts/upgrade_db.php: the script hard-codes IO2PATH="/opt/ioc2rpz.gui" and calls
 * upgradeSQLiteDB() at file scope, so including it would run the migration against
 * the production database. runMigration() below mirrors upgradeSQLiteDB()
 * statement-for-statement (same fall-through switch, same guard, same additive SQL)
 * driven through the real DB helpers, exactly as MigrationTest does for v2 -> v3.
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RateLimitMigrationTest extends TestCase
{
    /** Target schema version (matches DBVersion in scripts/upgrade_db.php). */
    private const TARGET_VERSION = 4;

    /** Pre-migration (v3) `servers` columns: everything up to and including track_default. */
    private const SERVERS_V3_COLUMNS =
        'user_id integer, name text, ip text, pub_ip text, ns text, email text, ' .
        'mgmt integer, disabled integer, stype integer, URL text, cfg_updated integer, ' .
        'publish_upd integer, certfile text, keyfile text, cacertfile text, custom_config text, ' .
        "track_default text default 'off'";

    /** Pre-migration (v3) `rpzs` columns. */
    private const RPZS_V3_COLUMNS =
        'user_id integer, name text, soa_refresh integer, soa_update_retry integer, ' .
        'soa_expiration integer, soa_nx_ttl integer, cache integer, wildcard integer, ' .
        'action text, ioc_type text, axfr_update integer, ixfr_update integer, disabled integer, ' .
        "track_sources text default 'Inherit'";

    /** The columns the v3 -> v4 step must add. */
    private const NEW_SERVER_COLUMNS = ['rl_window', 'rl_max_requests', 'rl_max_unknown_requests'];
    private const NEW_RPZ_COLUMNS = ['rl_window', 'rl_max_requests'];

    private string $dbFile = '';

    protected function setUp(): void
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'io2rlmig_') ?: '';
        $this->assertNotSame('', $this->dbFile, 'Failed to allocate a temp DB file');
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
     * Reproduces scripts/upgrade_db.php::upgradeSQLiteDB(), including the earlier
     * fall-through cases so a DB seeded at any version migrates through every step.
     *
     * @return array{db_version:int, executed:bool, result:mixed}
     */
    private function runMigration(string $dbFile): array
    {
        $db = new SQLite3($dbFile);
        $db_version = (int) (DB_selectArray($db, 'PRAGMA user_version')[0]['user_version']);

        $sql = '';
        switch ($db_version) {
            case 0:
                $sql .= 'alter table whitelists add column userid text default NULL;';
                $sql .= 'alter table whitelists add column max_ioc integer default 0;';
                $sql .= 'alter table whitelists add column hotcache_time integer default 900;';
                $sql .= 'alter table whitelists add column hotcacheixfr_time integer default 0;';
                $sql .= 'alter table sources add column userid text default NULL;';
                $sql .= 'alter table sources add column max_ioc integer default 0;';
                $sql .= 'alter table sources add column hotcache_time integer default 900;';
                $sql .= 'alter table sources add column hotcacheixfr_time integer default 0;';
                // no break - fall through
            case 1:
                $sql .= "alter table whitelists ADD column ioc_type text default 'mixed';";
                $sql .= 'alter table whitelists ADD column keep_in_cache integer default 0;';
                $sql .= "alter table sources ADD column ioc_type text default 'mixed';";
                $sql .= 'alter table sources ADD column keep_in_cache integer default 0;';
                // no break - fall through
            case 2:
                $sql .= "alter table servers add column track_default text default 'off';";
                $sql .= "alter table rpzs add column track_sources text default 'Inherit';";
                // no break - fall through
            case 3:
                $sql .= 'alter table servers add column rl_window integer default NULL;';
                $sql .= 'alter table servers add column rl_max_requests integer default NULL;';
                $sql .= 'alter table servers add column rl_max_unknown_requests integer default NULL;';
                $sql .= 'alter table rpzs add column rl_window integer default NULL;';
                $sql .= 'alter table rpzs add column rl_max_requests integer default NULL;';
                // no break - fall through
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
     * Creates a version-3 database with varied server/feed rows.
     *
     * @param bool $preAddRlWindow When true, `servers` already has rl_window so the
     *        migration's first new statement fails - used to force a failure.
     */
    private function seedV3Database(bool $preAddRlWindow = false): void
    {
        $db = new SQLite3($this->dbFile);

        $serversColumns = self::SERVERS_V3_COLUMNS;
        if ($preAddRlWindow) {
            $serversColumns .= ', rl_window integer default NULL';
        }

        $db->exec('PRAGMA user_version=3;');
        $db->exec("create table servers ($serversColumns);");
        $db->exec('create table rpzs (' . self::RPZS_V3_COLUMNS . ');');

        $serverValues = $preAddRlWindow
            ? "(1,'server_a','10.0.0.1','203.0.113.1','ns1.example','a@example',1,0,0,'a.conf',1,1,'a.pem','a.key','','cfgA','off',NULL)," .
              "(2,'server_b','10.0.0.2','203.0.113.2','ns2.example','b@example',0,1,2,'b.conf',0,0,'','','ca.pem','','auto',NULL)"
            : "(1,'server_a','10.0.0.1','203.0.113.1','ns1.example','a@example',1,0,0,'a.conf',1,1,'a.pem','a.key','','cfgA','off')," .
              "(2,'server_b','10.0.0.2','203.0.113.2','ns2.example','b@example',0,1,2,'b.conf',0,0,'','','ca.pem','','auto')";
        $db->exec("insert into servers values $serverValues;");

        $db->exec(
            'insert into rpzs values ' .
            "(1,'feed.a',86400,3600,2592000,7200,1,1,'nxdomain','mixed',604800,86400,0,'Inherit')," .
            "(2,'feed.b',3600,600,604800,300,0,0,'drop','ip',3600,900,1,'true');"
        );

        $db->close();
    }

    /** @return array<int,array<string,mixed>> */
    private function readRows(string $table, array $columns): array
    {
        $db   = new SQLite3($this->dbFile);
        $rows = DB_selectArray($db, 'select ' . implode(',', $columns) . " from $table order by rowid");
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

    /** @return string[] */
    private function tableColumns(string $table): array
    {
        $db   = new SQLite3($this->dbFile);
        $info = DB_selectArray($db, "PRAGMA table_info($table)");
        $db->close();

        return array_column($info, 'name');
    }

    /**
     * The v3 -> v4 migration adds all five columns, leaves every existing row's new
     * columns NULL (= inherit, so generated configs are unchanged), and preserves all
     * pre-upgrade field values including the v3 attribution settings.
     */
    public function testMigrationAddsColumnsAsNullAndPreservesExistingRows(): void
    {
        $this->seedV3Database();

        $serversBefore = $this->readRows('servers',
            ['rowid', 'name', 'ip', 'email', 'custom_config', 'disabled', 'track_default']);
        $rpzsBefore = $this->readRows('rpzs',
            ['rowid', 'name', 'action', 'cache', 'disabled', 'track_sources']);

        $outcome = $this->runMigration($this->dbFile);

        $this->assertTrue($outcome['executed'], 'Migration should run when version != target');
        $this->assertNotFalse($outcome['result'], 'Migration batch should succeed');
        $this->assertSame(self::TARGET_VERSION, $this->readUserVersion(), 'user_version advances to 4');

        // The columns exist.
        foreach (self::NEW_SERVER_COLUMNS as $col) {
            $this->assertContains($col, $this->tableColumns('servers'), "servers.$col added");
        }
        foreach (self::NEW_RPZ_COLUMNS as $col) {
            $this->assertContains($col, $this->tableColumns('rpzs'), "rpzs.$col added");
        }

        // Every existing row inherits (NULL), NOT a backfilled number.
        foreach ($this->readRows('servers', array_merge(['rowid'], self::NEW_SERVER_COLUMNS)) as $row) {
            foreach (self::NEW_SERVER_COLUMNS as $col) {
                $this->assertNull($row[$col], "existing servers.$col must be NULL (inherit)");
            }
        }
        foreach ($this->readRows('rpzs', array_merge(['rowid'], self::NEW_RPZ_COLUMNS)) as $row) {
            foreach (self::NEW_RPZ_COLUMNS as $col) {
                $this->assertNull($row[$col], "existing rpzs.$col must be NULL (inherit)");
            }
        }

        // All pre-upgrade values unchanged.
        $this->assertEquals($serversBefore, $this->readRows('servers',
            ['rowid', 'name', 'ip', 'email', 'custom_config', 'disabled', 'track_default']));
        $this->assertEquals($rpzsBefore, $this->readRows('rpzs',
            ['rowid', 'name', 'action', 'cache', 'disabled', 'track_sources']));
    }

    /**
     * An older database migrates through every intermediate step to v4 in one run,
     * which is what the fall-through switch exists for.
     */
    public function testMigrationFromVersionTwoReachesVersionFour(): void
    {
        // Seed a v2 DB: no track_* columns and no rl_* columns.
        $db = new SQLite3($this->dbFile);
        $db->exec('PRAGMA user_version=2;');
        $db->exec('create table servers (' .
            'user_id integer, name text, ip text, pub_ip text, ns text, email text, ' .
            'mgmt integer, disabled integer, stype integer, URL text, cfg_updated integer, ' .
            'publish_upd integer, certfile text, keyfile text, cacertfile text, custom_config text);');
        $db->exec('create table rpzs (' .
            'user_id integer, name text, soa_refresh integer, soa_update_retry integer, ' .
            'soa_expiration integer, soa_nx_ttl integer, cache integer, wildcard integer, ' .
            "action text, ioc_type text, axfr_update integer, ixfr_update integer, disabled integer);");
        $db->exec("insert into servers values (1,'s','10.0.0.1','203.0.113.1','ns','a@e',1,0,0,'c',0,0,'','','','');");
        $db->exec("insert into rpzs values (1,'f',3600,600,86400,300,1,1,'nxdomain','mixed',900,300,0);");
        $db->close();

        $outcome = $this->runMigration($this->dbFile);
        $this->assertNotFalse($outcome['result'], 'v2 -> v4 batch should succeed');
        $this->assertSame(self::TARGET_VERSION, $this->readUserVersion());

        // Both the v3 attribution columns and the v4 rate limit columns are present.
        $serverCols = $this->tableColumns('servers');
        $this->assertContains('track_default', $serverCols);
        foreach (self::NEW_SERVER_COLUMNS as $col) {
            $this->assertContains($col, $serverCols);
        }
        $rpzCols = $this->tableColumns('rpzs');
        $this->assertContains('track_sources', $rpzCols);
        foreach (self::NEW_RPZ_COLUMNS as $col) {
            $this->assertContains($col, $rpzCols);
        }

        // The v3 step's defaults are still applied, and the v4 columns are NULL.
        $srv = $this->readRows('servers', ['track_default', 'rl_window', 'rl_max_requests'])[0];
        $this->assertSame('off', $srv['track_default']);
        $this->assertNull($srv['rl_window']);
        $rpz = $this->readRows('rpzs', ['track_sources', 'rl_window', 'rl_max_requests'])[0];
        $this->assertSame('Inherit', $rpz['track_sources']);
        $this->assertNull($rpz['rl_max_requests']);
    }

    /**
     * Re-running against an already-upgraded DB is a no-op that preserves configured
     * rate limits, including an explicit 0.
     */
    public function testSecondRunIsNoOpPreservingConfiguredLimits(): void
    {
        $this->seedV3Database();
        $this->runMigration($this->dbFile);

        // An operator configures limits after the upgrade, including the 0 edge.
        $db = new SQLite3($this->dbFile);
        $db->exec('update servers set rl_window=30, rl_max_requests=0 where rowid=1;');
        $db->exec('update rpzs set rl_max_requests=20 where rowid=2;');
        $db->close();

        $serversBefore = $this->readRows('servers',
            array_merge(['rowid', 'name'], self::NEW_SERVER_COLUMNS));
        $rpzsBefore = $this->readRows('rpzs', array_merge(['rowid', 'name'], self::NEW_RPZ_COLUMNS));

        $outcome = $this->runMigration($this->dbFile);
        $this->assertFalse($outcome['executed'], 'Second run must not execute the batch');
        $this->assertSame(self::TARGET_VERSION, $this->readUserVersion(), 'Version stays at 4');

        $this->assertEquals($serversBefore, $this->readRows('servers',
            array_merge(['rowid', 'name'], self::NEW_SERVER_COLUMNS)));
        $this->assertEquals($rpzsBefore, $this->readRows('rpzs',
            array_merge(['rowid', 'name'], self::NEW_RPZ_COLUMNS)));

        // The 0 survived and did not become NULL.
        $this->assertSame(0, $this->readRows('servers', ['rl_max_requests'])[0]['rl_max_requests']);
        $this->assertSame(30, $this->readRows('servers', ['rl_window'])[0]['rl_window']);
        $this->assertSame(20, $this->readRows('rpzs', ['rl_max_requests'])[1]['rl_max_requests']);
    }

    /**
     * A failed upgrade does not advance user_version and leaves data intact; the
     * additive ADD COLUMN statements make no partial destructive change.
     */
    public function testFailedMigrationLeavesVersionAndDataIntact(): void
    {
        $this->seedV3Database(true);

        $serversBefore = $this->readRows('servers', ['rowid', 'name', 'ip', 'track_default']);
        $rpzsBefore    = $this->readRows('rpzs', ['rowid', 'name', 'action', 'track_sources']);

        $outcome = $this->runMigration($this->dbFile);

        $this->assertTrue($outcome['executed'], 'Batch runs while version=3');
        $this->assertFalse($outcome['result'], 'A failing upgrade returns an error indication');

        $this->assertSame(3, $this->readUserVersion(), 'Failed upgrade must not advance user_version');

        // The batch aborted on the duplicate servers.rl_window, so rpzs never changed.
        $this->assertNotContains('rl_window', $this->tableColumns('rpzs'), 'No partial schema change');

        $this->assertEquals($serversBefore, $this->readRows('servers', ['rowid', 'name', 'ip', 'track_default']));
        $this->assertEquals($rpzsBefore, $this->readRows('rpzs', ['rowid', 'name', 'action', 'track_sources']));
    }
}
