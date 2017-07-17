<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test;
use JKingWeb\Arsse\Exception;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\Date;

abstract class AbstractTest extends \PHPUnit\Framework\TestCase {

    function assertException(string $msg = "", string $prefix = "", string $type = "Exception") {
        if(func_num_args()) {
            $class = \JKingWeb\Arsse\NS_BASE . ($prefix !== "" ? str_replace("/", "\\", $prefix) . "\\" : "") . $type;
            $msgID = ($prefix !== "" ? $prefix . "/" : "") . $type. ".$msg";
            if(array_key_exists($msgID, Exception::CODES)) {
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

    function assertTime($exp, $test) {
        $exp  = Date::transform($exp, "iso8601");
        $test = Date::transform($test, "iso8601");
        $this->assertSame($exp, $test);
    }

    function clearData(bool $loadLang = true): bool {
        $r = new \ReflectionClass(\JKingWeb\Arsse\Arsse::class);
        $props = array_keys($r->getStaticProperties());
        foreach($props as $prop) {
            Arsse::$$prop = null;
        }
        if($loadLang) {
            Arsse::$lang = new \JKingWeb\Arsse\Lang();
        }
        return true;
    }
}