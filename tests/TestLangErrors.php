<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \org\bovigo\vfs\vfsStream;


class TestLangErrors extends \PHPUnit\Framework\TestCase {
	use TestingHelpers, LanguageTestingHelpers;

	static $vfs;
	static $path;
	static $files;
	static $defaultPath;

	function setUp() {
		Lang::set("", true);
	}

	function testLoadEmptyFile() {
		$this->assertException("fileCorrupt", "Lang");
		Lang::set("fr_ca", true);
	}

	function testLoadFileWhichDoesNotReturnAnArray() {
		$this->assertException("fileCorrupt", "Lang");
		Lang::set("it", true);
	}

	function testLoadFileWhichIsNotPhp() {
		$this->assertException("fileCorrupt", "Lang");
		Lang::set("ko", true);
	}

	function testLoadFileWhichIsCorrupt() {
		$this->assertException("fileCorrupt", "Lang");
		Lang::set("zh", true);
	}

	function testLoadFileWithooutReadPermission() {
		$this->assertException("fileUnreadable", "Lang");
		Lang::set("ru", true);
	}

	function testLoadSubtagOfMissingLanguage() {
		$this->assertException("fileMissing", "Lang");
		Lang::set("pt_br", true);
	}

	function testLoadMissingDefaultLanguage() {
		// this should be the last test of the series
		unlink(self::$path.Lang::DEFAULT.".php");
		$this->assertException("defaultFileMissing", "Lang");
		Lang::set("fr", true);
	}
}