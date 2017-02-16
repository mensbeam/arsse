<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \Webmozart\Glob\Glob;

class Lang {
    const DEFAULT = "en"; // fallback locale
    const REQUIRED = [    // collection of absolutely required strings to handle pathological errors
        'Exception.JKingWeb/NewsSync/Exception.uncoded'                     => 'The specified exception symbol {0} has no code specified in Exception.php',
        'Exception.JKingWeb/NewsSync/Exception.unknown'                     => 'An unknown error has occurred',
        'Exception.JKingWeb/NewsSync/Lang/Exception.defaultFileMissing'     => 'Default language file "{0}" missing',
        'Exception.JKingWeb/NewsSync/Lang/Exception.fileMissing'            => 'Language file "{0}" is not available',
        'Exception.JKingWeb/NewsSync/Lang/Exception.fileUnreadable'         => 'Insufficient permissions to read language file "{0}"',
        'Exception.JKingWeb/NewsSync/Lang/Exception.fileCorrupt'            => 'Language file "{0}" is corrupt or does not conform to expected format',
        'Exception.JKingWeb/NewsSync/Lang/Exception.stringMissing'          => 'Message string "{msgID}" missing from all loaded language files ({fileList})',
        'Exception.JKingWeb/NewsSync/Lang/Exception.stringInvalid'          => 'Message string "{msgID}" is not a valid ICU message string (language files loaded: {fileList})',
    ];

    static public    $path = BASE."locale".DIRECTORY_SEPARATOR; // path to locale files; this is a public property to facilitate unit testing
    static protected $requirementsMet = false;                  // whether the Intl extension is loaded
    static protected $synched = false;                          // whether the wanted locale is actually loaded (lazy loading is used by default)
    static protected $wanted = self::DEFAULT;                   // the currently requested locale
    static protected $locale = "";                              // the currently loaded locale
    static protected $loaded = [];                              // the cascade of loaded locale file names
    static protected $strings = self::REQUIRED;                 // the loaded locale strings, merged

    protected function __construct() {}

    static public function set(string $locale, bool $immediate = false): string {
        // make sure the Intl extension is loaded
        if(!self::$requirementsMet) self::checkRequirements();
        // if requesting the same locale as already wanted, just return (but load first if we've requested an immediate load)
        if($locale==self::$wanted) {
            if($immediate && !self::$synched) self::load();
            return $locale;
        }
        // if we've requested a locale other than the null locale, fetch the list of available files and find the closest match e.g. en_ca_somedialect -> en_ca
        if($locale != "") {
            $list = self::listFiles();
            // if the default locale is unavailable, this is (for now) an error
            if(!in_array(self::DEFAULT, $list)) throw new Lang\Exception("defaultFileMissing", self::DEFAULT);
            self::$wanted = self::match($locale, $list);
        } else {
            self::$wanted = "";
        }
        self::$synched = false;
        // load right now if asked to, otherwise load later when actually required
        if($immediate) self::load();
        return self::$wanted;
    }

    static public function get(bool $loaded = false): string {
        // we can either return the wanted locale (default) or the currently loaded locale
        return $loaded ? self::$locale : self::$wanted;
    }

    static public function dump(): array {
        return self::$strings;
    }

    static public function msg(string $msgID, $vars = null): string {
        // if we're trying to load the system default language and it fails, we have a chicken and egg problem, so we catch the exception and load no language file instead
        if(!self::$synched) try {self::load();} catch(Lang\Exception $e) {
            if(self::$wanted==self::DEFAULT) {
                self::set("", true);
            } else {
                throw $e;
            }
        }
        // if the requested message is not present in any of the currently loaded language files, throw an exception
        // note that this is indicative of a programming error since the default locale should have all strings
        if(!array_key_exists($msgID, self::$strings)) throw new Lang\Exception("stringMissing", ['msgID' => $msgID, 'fileList' => implode(", ",self::$loaded)]);
        $msg = self::$strings[$msgID];
        // variables fed to MessageFormatter must be contained in an array
        if($vars===null) {
            // even though strings not given parameters will not get formatted, we do not optimize this case away: we still want to catch invalid strings
            $vars = [];
        } else if(!is_array($vars)) {
            $vars = [$vars];
        }
        $msg = \MessageFormatter::formatMessage(self::$locale, $msg, $vars);
        if($msg===false) throw new Lang\Exception("stringInvalid", ['msgID' => $msgID, 'fileList' => implode(", ",self::$loaded)]);
        return $msg;
    }

    static public function list(string $locale = ""): array {
        $out = [];
        $files = self::listFiles();
        foreach($files as $tag) {
            $out[$tag] = \Locale::getDisplayName($tag, ($locale=="") ? $tag : $locale);
        }
        return $out;
    }

    static public function match(string $locale, array $list = null): string {
        if($list===null) $list = self::listFiles();
        $default = (self::$locale=="") ? self::DEFAULT : self::$locale;
        return \Locale::lookup($list,$locale, true, $default);
    }

    static protected function checkRequirements(): bool {
        if(!extension_loaded("intl")) throw new ExceptionFatal("The \"Intl\" extension is required, but not loaded");
        self::$requirementsMet = true;
        return true;
    }

    static protected function listFiles(): array {
        $out = glob(self::$path."*.php");
        // built-in glob doesn't work with vfsStream (and this other glob doesn't seem to work with Windows paths), so we try both
        if(empty($out)) $out = Glob::glob(self::$path."*.php"); // FIXME: we should just mock glob() in tests instead and make this a dev dependency
        // trim the returned file paths to return just the language tag
        $out = array_map(function($file) {
            $file = str_replace(DIRECTORY_SEPARATOR, "/", $file);
            $file = substr($file, strrpos($file, "/")+1);
            return strtolower(substr($file,0,strrpos($file,".")));
        },$out);
        // sort the results
        natsort($out);
        return $out;
    }

    static protected function load(): bool {
        if(!self::$requirementsMet) self::checkRequirements();
        // if we've requested no locale (""), just load the fallback strings and return
        if(self::$wanted=="") {
            self::$strings = self::REQUIRED;
            self::$locale = self::$wanted;
            self::$synched = true;
            return true;
        }
        // decompose the requested locale from specific to general, building a list of files to load
        $tags = \Locale::parseLocale(self::$wanted);
        $files = [];
        while(sizeof($tags) > 0) {
            $files[] = strtolower(\Locale::composeLocale($tags));
            $tag = array_pop($tags);
        }
        // include the default locale as the base if the most general locale requested is not the default
        if($tag != self::DEFAULT) $files[] = self::DEFAULT;
        // save the list of files to be loaded for later reference
        $loaded = $files;
        // reduce the list of files to be loaded to the minimum necessary (e.g. if we go from "fr" to "fr_ca", we don't need to load "fr" or "en")
        $files = [];
        foreach($loaded as $file) {
            if($file==self::$locale) break;
            $files[] = $file;
        }
        // if we need to load all files, start with the fallback strings
        $strings = [];
        if($files==$loaded) {
            $strings[] = self::REQUIRED;
        } else {
            // otherwise start with the strings we already have if we're going from e.g. "fr" to "fr_ca"
            $strings[] = self::$strings;
        }
        // read files in reverse order
        $files = array_reverse($files);
        foreach($files as $file) {
            if(!file_exists(self::$path."$file.php")) throw new Lang\Exception("fileMissing", $file);
            if(!is_readable(self::$path."$file.php")) throw new Lang\Exception("fileUnreadable", $file);
            try {
                // we use output buffering in case the language file is corrupted
                ob_start();
                $arr = (include self::$path."$file.php");
            } catch(\Throwable $e) {
                $arr = null;
            } finally {
                ob_end_clean();
            }
            if(!is_array($arr)) throw new Lang\Exception("fileCorrupt", $file);
            $strings[] = $arr;
        }
        // apply the results and return
        self::$strings = call_user_func_array("array_replace_recursive", $strings);
        self::$loaded = $loaded;
        self::$locale = self::$wanted;
        self::$synched = true;
        return true;
    }
}