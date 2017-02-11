<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \org\bovigo\vfs\vfsStream;


class TestLang extends \PHPUnit\Framework\TestCase {
	use TestingHelpers, LanguageTestingHelpers;

	static $vfs;
	static $path;
	static $files;
	static $defaultPath;

	function testListLanguages() {
		$this->assertCount(sizeof(self::$files), Lang::list("en"));
	}

	/**
     * @depends testListLanguages
     */
	function testSetLanguage() {
		$this->assertEquals("en", Lang::set("en"));
		$this->assertEquals("en_ca", Lang::set("en_ca"));
		$this->assertEquals("de", Lang::set("de_ch"));
		$this->assertEquals("en", Lang::set("en_gb_hixie"));
		$this->assertEquals("en_ca", Lang::set("en_ca_jking"));
		$this->assertEquals("en", Lang::set("es"));
		$this->assertEquals("", Lang::set(""));
	}

	/**
     * @depends testSetLanguage
     */
	function testLoadInternalStrings() {
		$this->assertEquals("", Lang::set("", true));
		$this->assertCount(sizeof(Lang::REQUIRED), Lang::dump());
	}

	/**
     * @depends testLoadInternalStrings
     */
	function testLoadDefaultLanguage() {
		$this->assertEquals(Lang::DEFAULT, Lang::set(Lang::DEFAULT, true));
		$str = Lang::dump();
		$this->assertArrayHasKey('Exception.JKingWeb/NewsSync/Exception.uncoded', $str);
		$this->assertArrayHasKey('Test.presentText', $str);
	}

	/**
     * @depends testLoadDefaultLanguage
     */
	function testLoadSupplementaryLanguage() {
		Lang::set(Lang::DEFAULT, true);
		$this->assertEquals("ja", Lang::set("ja", true));
		$str = Lang::dump();
		$this->assertArrayHasKey('Exception.JKingWeb/NewsSync/Exception.uncoded', $str);
		$this->assertArrayHasKey('Test.presentText', $str);
		$this->assertArrayHasKey('Test.absentText', $str);
	}

}