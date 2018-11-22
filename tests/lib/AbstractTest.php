<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test;

use JKingWeb\Arsse\Exception;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\CLI;
use JKingWeb\Arsse\Misc\Date;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\EmptyResponse;

/** @coversNothing */
abstract class AbstractTest extends \PHPUnit\Framework\TestCase {
    public function setUp() {
        $this->clearData();
    }

    public function tearDown() {
        $this->clearData();
    }

    public function clearData(bool $loadLang = true) {
        date_default_timezone_set("America/Toronto");
        $r = new \ReflectionClass(\JKingWeb\Arsse\Arsse::class);
        $props = array_keys($r->getStaticProperties());
        foreach ($props as $prop) {
            Arsse::$$prop = null;
        }
        if ($loadLang) {
            Arsse::$lang = new \JKingWeb\Arsse\Lang();
        }
    }

    public function setConf(array $conf = []) {
        $defaults = [
            'dbSQLite3File' => ":memory:",
            'dbSQLite3Timeout' => 0,
            'dbPostgreSQLUser' => "arsse_test",
            'dbPostgreSQLPass' => "arsse_test",
            'dbPostgreSQLDb' => "arsse_test",
        ];
        Arsse::$conf = Arsse::$conf ?? (new Conf)->import($defaults)->import($conf);
    }

    public function assertException(string $msg = "", string $prefix = "", string $type = "Exception") {
        if (func_num_args()) {
            $class = \JKingWeb\Arsse\NS_BASE . ($prefix !== "" ? str_replace("/", "\\", $prefix) . "\\" : "") . $type;
            $msgID = ($prefix !== "" ? $prefix . "/" : "") . $type. ".$msg";
            if (array_key_exists($msgID, Exception::CODES)) {
                $code = Exception::CODES[$msgID];
            } else {
                $code = 0;
            }
            $this->expectException($class);
            $this->expectExceptionCode($code);
        } else {
            // expecting a standard PHP exception
            $this->expectException(\Exception::class);
        }
    }

    protected function assertMessage(MessageInterface $exp, MessageInterface $act, string $text = null) {
        if ($exp instanceof ResponseInterface) {
            $this->assertInstanceOf(ResponseInterface::class, $act, $text);
            $this->assertEquals($exp->getStatusCode(), $act->getStatusCode(), $text);
        } elseif ($exp instanceof RequestInterface) {
            if ($exp instanceof ServerRequestInterface) {
                $this->assertInstanceOf(ServerRequestInterface::class, $act, $text);
                $this->assertEquals($exp->getAttributes(), $act->getAttributes(), $text);
            }
            $this->assertInstanceOf(RequestInterface::class, $act, $text);
            $this->assertSame($exp->getMethod(), $act->getMethod(), $text);
            $this->assertSame($exp->getRequestTarget(), $act->getRequestTarget(), $text);
        }
        if ($exp instanceof JsonResponse) {
            $this->assertEquals($exp->getPayload(), $act->getPayload(), $text);
            $this->assertSame($exp->getPayload(), $act->getPayload(), $text);
        } else {
            $this->assertEquals((string) $exp->getBody(), (string) $act->getBody(), $text);
        }
        $this->assertEquals($exp->getHeaders(), $act->getHeaders(), $text);
    }

    public function assertTime($exp, $test, string $msg = null) {
        $test = $this->approximateTime($exp, $test);
        $exp  = Date::transform($exp, "iso8601");
        $test = Date::transform($test, "iso8601");
        $this->assertSame($exp, $test, $msg);
    }

    public function approximateTime($exp, $act) {
        if (is_null($act)) {
            return null;
        } elseif (is_null($exp)) {
            return $act;
        }
        $target = Date::normalize($exp)->getTimeStamp();
        $value = Date::normalize($act)->getTimeStamp();
        if ($value >= ($target - 1) && $value <= ($target + 1)) {
            // if the actual time is off by no more than one second, it's acceptable
            return $exp;
        } else {
            return $act;
        }
    }

    public function stringify($value) {
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->v($v);
            } elseif (is_int($v) || is_float($v)) {
                $value[$k] = (string) $v;
            }
        }
        return $value;
    }

    public function provideDbDrivers(array $conf = []): array {
        $this->setConf($conf);
        return [
            'SQLite 3' => (function() {
                try {
                    return new \JKingWeb\Arsse\Db\SQLite3\Driver;
                } catch (\Exception $e) {
                    return;
                }
            })(),
            'PDO SQLite 3' => (function() {
                try {
                    return new \JKingWeb\Arsse\Db\SQLite3\PDODriver;
                } catch (\Exception $e) {
                    return;
                }
            })(),
            'PDO PostgreSQL' => (function() {
                try {
                    return new \JKingWeb\Arsse\Db\PostgreSQL\PDODriver;
                } catch (\Exception $e) {
                    return;
                }
            })(),
        ];
    }

    public function provideDbInterfaces(array $conf = []): array {
        $this->setConf($conf);
        return [
            'SQLite 3' => [
                'interface' => (function() {
                    if (\JKingWeb\Arsse\Db\SQLite3\Driver::requirementsMet()) {
                        try {
                            $d = new \SQLite3(Arsse::$conf->dbSQLite3File);
                        } catch (\Exception $e) {
                            return;
                        }
                        $d->enableExceptions(true);
                        return $d;
                    }
                })(),
                'statement' => \JKingWeb\Arsse\Db\SQLite3\Statement::class,
                'result' => \JKingWeb\Arsse\Db\SQLite3\Result::class,
                'stringOutput' => false,
            ],
            'PDO SQLite 3' => [
                'interface' => (function() {
                    if (\JKingWeb\Arsse\Db\SQLite3\PDODriver::requirementsMet()) {
                        try {
                            return new \PDO("sqlite:".Arsse::$conf->dbSQLite3File, "", "", [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                        } catch (\PDOException $e) {
                            return;
                        }
                    }
                })(),
                'statement' => \JKingWeb\Arsse\Db\PDOStatement::class,
                'result' => \JKingWeb\Arsse\Db\PDOResult::class,
                'stringOutput' => true,
            ],
            'PDO PostgreSQL' => [
                'interface' => (function() {
                    if (\JKingWeb\Arsse\Db\PostgreSQL\PDODriver::requirementsMet()) {
                        $connString = \JKingWeb\Arsse\Db\PostgreSQL\Driver::makeConnectionString(true, Arsse::$conf->dbPostgreSQLUser, Arsse::$conf->dbPostgreSQLPass, Arsse::$conf->dbPostgreSQLDb, Arsse::$conf->dbPostgreSQLHost, Arsse::$conf->dbPostgreSQLPort, "");
                        try {
                            $c = new \PDO("pgsql:".$connString, Arsse::$conf->dbPostgreSQLUser, Arsse::$conf->dbPostgreSQLPass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                        } catch (\PDOException $e) {
                            return;
                        }
                        foreach (\JKingWeb\Arsse\Db\PostgreSQL\PDODriver::makeSetupQueries(Arsse::$conf->dbPostgreSQLSchema) as $q) {
                            $c->exec($q);
                        }
                        return $c;
                    }
                })(),
                'statement' => \JKingWeb\Arsse\Db\PDOStatement::class,
                'result' => \JKingWeb\Arsse\Db\PDOResult::class,
                'stringOutput' => true,
            ],
        ];
    }

    public function getDbDriver(string $name, array $conf = []) {
        $this->setConf($conf);
        switch ($name) {
            case 'SQLite 3':
                return (function() {
                    try {
                        return new \JKingWeb\Arsse\Db\SQLite3\Driver;
                    } catch (\Exception $e) {
                        return;
                    }
                })();
            case 'PDO SQLite 3':
                return (function() {
                    try {
                        return new \JKingWeb\Arsse\Db\SQLite3\PDODriver;
                    } catch (\Exception $e) {
                        return;
                    }
                })();
            case 'PDO PostgreSQL':
                return (function() {
                    try {
                        return new \JKingWeb\Arsse\Db\PostgreSQL\PDODriver;
                    } catch (\Exception $e) {
                        return;
                    }
                })();
            default:
                throw new \Exception("Invalid database driver name");
        }
    }

    public function getDbInterface(string $name, array $conf = []) {
        $this->setConf($conf);
        switch ($name) {
            case 'SQLite 3':
                return (function() {
                    if (\JKingWeb\Arsse\Db\SQLite3\Driver::requirementsMet()) {
                        try {
                            $d = new \SQLite3(Arsse::$conf->dbSQLite3File);
                        } catch (\Exception $e) {
                            return;
                        }
                        $d->enableExceptions(true);
                        return $d;
                    }
                })();
            case 'PDO SQLite 3':
                return (function() {
                    if (\JKingWeb\Arsse\Db\SQLite3\PDODriver::requirementsMet()) {
                        try {
                            $d = new \PDO("sqlite:".Arsse::$conf->dbSQLite3File, "", "", [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                            $d->exec("PRAGMA busy_timeout=0");
                            return $d;
                        } catch (\PDOException $e) {
                            return;
                        }
                    }
                })();
            case 'PDO PostgreSQL':
                return (function() {
                    if (\JKingWeb\Arsse\Db\PostgreSQL\PDODriver::requirementsMet()) {
                        $connString = \JKingWeb\Arsse\Db\PostgreSQL\Driver::makeConnectionString(true, Arsse::$conf->dbPostgreSQLUser, Arsse::$conf->dbPostgreSQLPass, Arsse::$conf->dbPostgreSQLDb, Arsse::$conf->dbPostgreSQLHost, Arsse::$conf->dbPostgreSQLPort, "");
                        try {
                            $c = new \PDO("pgsql:".$connString, Arsse::$conf->dbPostgreSQLUser, Arsse::$conf->dbPostgreSQLPass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                        } catch (\PDOException $e) {
                            return;
                        }
                        foreach (\JKingWeb\Arsse\Db\PostgreSQL\PDODriver::makeSetupQueries(Arsse::$conf->dbPostgreSQLSchema) as $q) {
                            $c->exec($q);
                        }
                        return $c;
                    }
                })();
            default:
                throw new \Exception("Invalid database driver name");
        }
    }
}
