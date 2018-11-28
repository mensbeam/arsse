<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\PosgreSQL;

/** 
 * @covers \JKingWeb\Arsse\Database<extended>
 * @covers \JKingWeb\Arsse\Misc\Query<extended>
 */
class TestDatabase extends \JKingWeb\Arsse\TestCase\Database\Base {
    protected static $implementation = "PDO PostgreSQL";

    protected function nextID(string $table): int {
        return ((int) static::$drv->query("SELECT last_value from pg_sequences where sequencename = '{$table}_id_seq'")->getValue()) + 1;
    }

    public function setUp() {
        parent::setUp();
        $seqList = 
            "select 
                replace(substring(column_default, 10), right(column_default, 12), '') as seq, 
                table_name as table, 
                column_name as col 
            from information_schema.columns 
                where table_name like 'arsse_%' 
                and column_default like 'nextval(%'
            ";
        foreach(static::$drv->query($seqList) as $r) {
            $num = static::$drv->query("SELECT max({$r['col']}) from {$r['table']}")->getValue();
            if (!$num) {
                continue;
            }
            $num++;
            static::$drv->exec("ALTER SEQUENCE {$r['seq']} RESTART WITH $num");
        }
    }
}
