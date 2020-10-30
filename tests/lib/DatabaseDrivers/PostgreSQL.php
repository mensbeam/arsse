<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test\DatabaseDrivers;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Driver;

trait PostgreSQL {
    protected static $implementation = "PostgreSQL";
    protected static $backend = "PostgreSQL";
    protected static $dbResultClass = \JKingWeb\Arsse\Db\PostgreSQL\Result::class;
    protected static $dbStatementClass = \JKingWeb\Arsse\Db\PostgreSQL\Statement::class;
    protected static $dbDriverClass = \JKingWeb\Arsse\Db\PostgreSQL\Driver::class;
    protected static $stringOutput = true;

    public static function dbInterface() {
        $connString = \JKingWeb\Arsse\Db\PostgreSQL\Driver::makeConnectionString(false, Arsse::$conf->dbPostgreSQLUser, Arsse::$conf->dbPostgreSQLPass, Arsse::$conf->dbPostgreSQLDb, Arsse::$conf->dbPostgreSQLHost, Arsse::$conf->dbPostgreSQLPort, "");
        if (function_exists("pg_connect") && $d = @pg_connect($connString, \PGSQL_CONNECT_FORCE_NEW)) {
            foreach (\JKingWeb\Arsse\Db\PostgreSQL\Driver::makeSetupQueries(Arsse::$conf->dbPostgreSQLSchema) as $q) {
                pg_query($d, $q);
            }
            return $d;
        } else {
            return;
        }
    }

    public static function dbExec($db, $q): void {
        if ($db instanceof Driver) {
            $db->exec($q);
        } elseif ($db instanceof \PDO) {
            $db->exec($q);
        } else {
            pg_query($db, $q);
        }
    }

    public static function dbTableList($db): array {
        $listObjects = "SELECT table_name as name, 'TABLE' as type from information_schema.tables where table_schema = current_schema() and table_name like 'arsse_%' union SELECT collation_name as name, 'COLLATION' as type from information_schema.collations where collation_schema = current_schema()";
        if ($db instanceof Driver) {
            return $db->query($listObjects)->getAll();
        } elseif ($db instanceof \PDO) {
            return $db->query($listObjects)->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $r = @pg_query($db, $listObjects);
            $out = $r ? pg_fetch_all($r) : false;
            return $out ? $out : [];
        }
    }

    public static function dbTruncate($db, array $afterStatements = []): void {
        // rollback any pending transaction
        try {
            @self::dbExec($db, "ROLLBACK");
        } catch (\Throwable $e) {
        }
        foreach (self::dbTableList($db) as $obj) {
            if ($obj['type'] !== "TABLE") {
                continue;
            } elseif ($obj['name'] === "arsse_meta") {
                self::dbExec($db, "DELETE FROM {$obj['name']} where key <> 'schema_version'");
            } else {
                self::dbExec($db, "TRUNCATE TABLE {$obj['name']} restart identity cascade");
            }
        }
        foreach ($afterStatements as $st) {
            self::dbExec($db, $st);
        }
    }

    public static function dbRaze($db, array $afterStatements = []): void {
        // rollback any pending transaction
        try {
            @self::dbExec($db, "ROLLBACK");
        } catch (\Throwable $e) {
        }
        foreach (self::dbTableList($db) as $obj) {
            self::dbExec($db, "DROP {$obj['type']} IF EXISTS {$obj['name']} cascade");
        }
        foreach ($afterStatements as $st) {
            self::dbExec($db, $st);
        }
    }
}
