<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\User;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\AbstractException as Exception;
use JKingWeb\Arsse\User\ExceptionConflict;
use JKingWeb\Arsse\User\Driver;

/** @covers \JKingWeb\Arsse\User */
class TestUser extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        self::clearData();
        self::setConf();
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(\JKingWeb\Arsse\Db\Transaction::class));
        // create a mock user driver
        $this->drv = \Phake::mock(Driver::class);
    }
    
    public function tearDown(): void {
        \Phake::verifyNoOtherInteractions($this->drv);
        \Phake::verifyNoOtherInteractions(Arsse::$db);
    }

    public function testConstruct(): void {
        $this->assertInstanceOf(User::class, new User($this->drv));
        $this->assertInstanceOf(User::class, new User);
    }

    public function testConversionToString(): void {
        $u = new User;
        $u->id = "john.doe@example.com";
        $this->assertSame("john.doe@example.com", (string) $u);
        $u->id = null;
        $this->assertSame("", (string) $u);
    }

    /** @dataProvider provideAuthentication */
    public function testAuthenticateAUser(bool $preAuth, string $user, string $password, bool $exp): void {
        Arsse::$conf->userPreAuth = $preAuth;
        \Phake::when($this->drv)->auth->thenReturn(false);
        \Phake::when($this->drv)->auth("john.doe@example.com", "secret")->thenReturn(true);
        \Phake::when($this->drv)->auth("jane.doe@example.com", "superman")->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists("john.doe@example.com")->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists("jane.doe@example.com")->thenReturn(false);
        \Phake::when(Arsse::$db)->userAdd->thenReturn("");
        $u = new User($this->drv);
        $this->assertSame($exp, $u->auth($user, $password));
        $this->assertNull($u->id);
        \Phake::verify($this->drv, \Phake::times((int) !$preAuth))->auth($user, $password);
        \Phake::verify(Arsse::$db, \Phake::times($exp ? 1 : 0))->userExists($user);
        \Phake::verify(Arsse::$db, \Phake::times($exp && $user === "jane.doe@example.com" ? 1 : 0))->userAdd($user, $password);
    }

    public function provideAuthentication(): iterable {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        return [
            [false, $john, "secret",   true],
            [false, $john, "superman", false],
            [false, $jane, "secret",   false],
            [false, $jane, "superman", true],
            [true,  $john, "secret",   true],
            [true,  $john, "superman", true],
            [true,  $jane, "secret",   true],
            [true,  $jane, "superman", true],
        ];
    }

    public function testListUsers(): void {
        $exp = ["john.doe@example.com", "jane.doe@example.com"];
        $u = new User($this->drv);
        \Phake::when($this->drv)->userList->thenReturn(["john.doe@example.com", "jane.doe@example.com"]);
        $this->assertSame($exp, $u->list());
        \Phake::verify($this->drv)->userList();
    }

    public function testAddAUser(): void {
        $user = "ohn.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userAdd->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertSame($pass, $u->add($user, $pass));
        \Phake::verify($this->drv)->userAdd($user, $pass);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testAddAUserWeDoNotKnow(): void {
        $user = "ohn.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userAdd->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertSame($pass, $u->add($user, $pass));
        \Phake::verify($this->drv)->userAdd($user, $pass);
        \Phake::verify(Arsse::$db)->userExists($user);
        \Phake::verify(Arsse::$db)->userAdd($user, $pass);
    }

    public function testAddADuplicateUser(): void {
        $user = "ohn.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userAdd->thenThrow(new ExceptionConflict("alreadyExists"));
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertException("alreadyExists", "User", "ExceptionConflict");
        try {
            $u->add($user, $pass);
        } finally {
            \Phake::verify(Arsse::$db)->userExists($user);
            \Phake::verify($this->drv)->userAdd($user, $pass);
        }
    }

    public function testAddADuplicateUserWeDoNotKnow(): void {
        $user = "ohn.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userAdd->thenThrow(new ExceptionConflict("alreadyExists"));
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertException("alreadyExists", "User", "ExceptionConflict");
        try {
            $u->add($user, $pass);
        } finally {
            \Phake::verify(Arsse::$db)->userExists($user);
            \Phake::verify(Arsse::$db)->userAdd($user, null);
            \Phake::verify($this->drv)->userAdd($user, $pass);
        }
    }
}
