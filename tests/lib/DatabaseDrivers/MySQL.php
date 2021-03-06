<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test\DatabaseDrivers;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Driver;

trait MySQL {
    protected static $implementation = "MySQL";
    protected static $backend = "MySQL";
    protected static $dbResultClass = \JKingWeb\Arsse\Db\MySQL\Result::class;
    protected static $dbStatementClass = \JKingWeb\Arsse\Db\MySQL\Statement::class;
    protected static $dbDriverClass = \JKingWeb\Arsse\Db\MySQL\Driver::class;
    protected static $stringOutput = true;

    public static function dbInterface() {
        if (!class_exists("mysqli")) {
            return null;
        }
        $d = mysqli_init();
        $d->options(\MYSQLI_OPT_INT_AND_FLOAT_NATIVE, false);
        $d->options(\MYSQLI_SET_CHARSET_NAME, "utf8mb4");
        @$d->real_connect(Arsse::$conf->dbMySQLHost, Arsse::$conf->dbMySQLUser, Arsse::$conf->dbMySQLPass, Arsse::$conf->dbMySQLDb, Arsse::$conf->dbMySQLPort);
        if ($d->connect_errno) {
            return null;
        }
        $d->set_charset("utf8mb4");
        foreach (\JKingWeb\Arsse\Db\MySQL\PDODriver::makeSetupQueries() as $q) {
            $d->query($q);
        }
        return $d;
    }

    public static function dbTableList($db): array {
        $listTables = "SELECT table_name as name from information_schema.tables where table_schema = database() and table_name like 'arsse_%'";
        if ($db instanceof Driver) {
            $tables = $db->query($listTables)->getAll();
        } elseif ($db instanceof \PDO) {
            $tables = $db->query($listTables)->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $tables = $db->query($listTables)->fetch_all(\MYSQLI_ASSOC);
        }
        $tables = sizeof($tables) ? array_column($tables, "name") : [];
        return $tables;
    }

    public static function dbTruncate($db, array $afterStatements = []): void {
        // rollback any pending transaction
        try {
            $db->query("UNLOCK TABLES; ROLLBACK");
        } catch (\Throwable $e) {
        }
        $db->query("SET FOREIGN_KEY_CHECKS=0");
        foreach (self::dbTableList($db) as $table) {
            if ($table === "arsse_meta") {
                $db->query("DELETE FROM $table where `key` <> 'schema_version'");
            } else {
                $db->query("DELETE FROM $table");
            }
            $db->query("ALTER TABLE $table auto_increment = 1");
        }
        $db->query("SET FOREIGN_KEY_CHECKS=1");
        foreach ($afterStatements as $st) {
            $db->query($st);
        }
    }

    public static function dbRaze($db, array $afterStatements = []): void {
        // rollback any pending transaction
        try {
            $db->query("UNLOCK TABLES; ROLLBACK");
        } catch (\Throwable $e) {
        }
        $db->query("SET FOREIGN_KEY_CHECKS=0");
        foreach (self::dbTableList($db) as $table) {
            $db->query("DROP TABLE IF EXISTS $table");
        }
        $db->query("SET FOREIGN_KEY_CHECKS=1");
        foreach ($afterStatements as $st) {
            $db->query($st);
        }
    }
}
