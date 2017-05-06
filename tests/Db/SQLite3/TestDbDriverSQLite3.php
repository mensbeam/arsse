<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;


class TestDbDriverSQLite3 extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

    protected $data;
    protected $drv;

    function setUp() {
        $this->clearData();
        $conf = new Conf();
        $conf->dbDriver = Db\SQLite3\Driver::class;
        $conf->dbSQLite3File = tempnam(sys_get_temp_dir(), 'ook');
        Data::$conf = $conf;
        $this->drv = new Db\SQLite3\Driver(true);
    }

    function tearDown() {
        unset($this->drv);
        unlink(Data::$conf->dbSQLite3File);
        $this->clearData();
    }

    function testFetchDriverName() {
        $class = Data::$conf->dbDriver;
        $this->assertTrue(strlen($class::driverName()) > 0);
    }

    function testExecAValidStatement() {
        $this->assertTrue($this->drv->exec("CREATE TABLE test(id integer primary key)"));
    }

    function testExecAnInvalidStatement() {
        $this->assertException("engineErrorGeneral", "Db");
        $this->drv->exec("And the meek shall inherit the earth...");
    }

    function testExecMultipleStatements() {
        $this->assertTrue($this->drv->exec("CREATE TABLE test(id integer primary key); INSERT INTO test(id) values(2112)"));
        $ch = new \SQLite3(Data::$conf->dbSQLite3File);
        $this->assertEquals(2112, $ch->querySingle("SELECT id from test"));
    }

    function testExecTimeout() {
        $ch = new \SQLite3(Data::$conf->dbSQLite3File);
        $ch->exec("BEGIN EXCLUSIVE TRANSACTION");
        $this->assertException("general", "Db", "ExceptionTimeout");
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
    }

    function testExecConstraintViolation() {
        $this->drv->exec("CREATE TABLE test(id integer not null)");
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        $this->drv->exec("INSERT INTO test(id) values(null)");
    }

    function testExecTypeViolation() {
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->drv->exec("INSERT INTO test(id) values('ook')");
    }

    function testMakeAValidQuery() {
        $this->assertInstanceOf(Db\SQLite3\Result::class, $this->drv->query("SELECT 1"));
    }

    function testMakeAnInvalidQuery() {
        $this->assertException("engineErrorGeneral", "Db");
        $this->drv->query("Apollo was astonished; Dionysus thought me mad");
    }

    function testQueryTimeout() {
        $ch = new \SQLite3(Data::$conf->dbSQLite3File);
        $ch->exec("BEGIN EXCLUSIVE TRANSACTION");
        $this->assertException("general", "Db", "ExceptionTimeout");
        $this->drv->query("CREATE TABLE test(id integer primary key)");
    }

    function testQueryConstraintViolation() {
        $this->drv->exec("CREATE TABLE test(id integer not null)");
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        $this->drv->query("INSERT INTO test(id) values(null)");
    }

    function testQueryTypeViolation() {
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->drv->query("INSERT INTO test(id) values('ook')");
    }

    function testPrepareAValidQuery() {
        $s = $this->drv->prepare("SELECT ?, ?", "int", "int");
        $this->assertInstanceOf(Db\SQLite3\Statement::class, $s);
    }

    function testPrepareAnInvalidQuery() {
        $this->assertException("engineErrorGeneral", "Db");
        $s = $this->drv->prepare("This is an invalid query", "int", "int");
    }

    function testBeginTransaction() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $ch = new \SQLite3(Data::$conf->dbSQLite3File);
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
    }

    function testCommitTransaction() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $ch = new \SQLite3(Data::$conf->dbSQLite3File);
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $tr->commit();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(1, $ch->querySingle($select));
    }

    function testRollbackTransaction() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $ch = new \SQLite3(Data::$conf->dbSQLite3File);
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $tr->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
    }

    function testBeginChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $ch = new \SQLite3(Data::$conf->dbSQLite3File);
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
    }

    function testCommitChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $ch = new \SQLite3(Data::$conf->dbSQLite3File);
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $tr2->commit();
        $this->assertEquals(0, $ch->querySingle($select));
        $tr1->commit();
        $this->assertEquals(2, $ch->querySingle($select));
    }

    function testRollbackChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $ch = new \SQLite3(Data::$conf->dbSQLite3File);
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $tr2->rollback();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $tr1->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
    }

    function testPartiallyRollbackChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $ch = new \SQLite3(Data::$conf->dbSQLite3File);
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $tr2->rollback();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $ch->querySingle($select));
        $tr1->commit();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(1, $ch->querySingle($select));
    }

    function testFetchSchemaVersion() {
        $this->assertSame(0, $this->drv->schemaVersion());
        $this->drv->exec("PRAGMA user_version=1");
        $this->assertSame(1, $this->drv->schemaVersion());
        $this->drv->exec("PRAGMA user_version=2");
        $this->assertSame(2, $this->drv->schemaVersion());

    }

    function testManipulateAdvisoryLock() {
        $this->assertTrue($this->drv->unlock());
        $this->assertFalse($this->drv->isLocked());
        $this->assertTrue($this->drv->lock());
        $this->assertFalse($this->drv->isLocked());
        $this->drv->exec("CREATE TABLE arsse_settings(key primary key, value, type) without rowid; PRAGMA user_version=1");
        $this->assertTrue($this->drv->lock());
        $this->assertTrue($this->drv->isLocked());
        $this->assertFalse($this->drv->lock());
        $this->drv->exec("PRAGMA user_version=0");
        $this->assertFalse($this->drv->isLocked());
        $this->assertTrue($this->drv->lock());
        $this->assertFalse($this->drv->isLocked());
        $this->drv->exec("PRAGMA user_version=1");
        $this->assertTrue($this->drv->isLocked());
        $this->assertTrue($this->drv->unlock());
        $this->assertFalse($this->drv->isLocked());
    }
}