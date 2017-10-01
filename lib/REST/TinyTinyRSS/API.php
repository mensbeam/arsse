<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\TinyTinyRSS;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\Misc\Context;
use JKingWeb\Arsse\Misc\ValueInfo;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\REST\Response;

/*

Protocol difference so far:
    - handling of incorrect Content-Type and/or HTTP method is different
    - TT-RSS accepts whitespace-only names; we do not
    - TT-RSS allows two folders to share the same name under the same parent; we do not
    - Session lifetime is much shorter by default (does TT-RSS even expire sessions?)

*/



class API extends \JKingWeb\Arsse\REST\AbstractHandler {
    const LEVEL = 14;
    const VERSION = "17.4";
    const FATAL_ERR = [
        'seq' => null,
        'status' => 1,
        'content' => ['error' => "NOT_LOGGED_IN"],
    ];
    const OVERRIDE = [
        'auth' => ["login"],
    ];
    
    public function __construct() {
    }

    public function dispatch(\JKingWeb\Arsse\REST\Request $req): Response {
        if ($req->method != "POST") {
            // only POST requests are allowed
            return new Response(405, self::FATAL_ERR, "application/json", ["Allow: POST"]);
        }
        if ($req->body) {
            // only JSON entities are allowed
            if (!preg_match("<^application/json\b|^$>", $req->type)) {
                return new Response(415, self::FATAL_ERR, "application/json", ['Accept: application/json']);
            }
            $data = @json_decode($req->body, true);
            if (json_last_error() != \JSON_ERROR_NONE || !is_array($data)) {
                // non-JSON input indicates an error
                return new Response(400, self::FATAL_ERR);
            }
            // layer input over defaults
            $data = array_merge([
                'seq' => 0,
                'op'  => "",
                'sid' => null,
            ], $data);
            try {
                if (!in_array($data['op'], self::OVERRIDE['auth'])) {
                    // unless otherwise specified, a session identifier is required
                    $this->resumeSession($data['sid']);
                }
                $method = "op".ucfirst($data['op']);
                if (!method_exists($this, $method)) {
                    // because method names are supposed to be case insensitive, we need to try a bit harder to match
                    $method = strtolower($method);
                    $map = get_class_methods($this);
                    $map = array_combine(array_map("strtolower", $map), $map);
                    if (!array_key_exists($method, $map)) {
                        // if the method really doesn't exist, throw an exception
                        throw new Exception("UNKNWON_METHOD", ['method' => $data['op']]);
                    }
                    // otherwise retrieve the correct camelCase and continue
                    $method = $map[$method];
                }
                return new Response(200, [
                    'seq' => $data['seq'],
                    'status' => 0,
                    'content' => $this->$method($data),
                ]);
            } catch (Exception $e) {
                return new Response(200, [
                    'seq' => $data['seq'],
                    'status' => 1,
                    'content' => $e->getData(),
                ]);
            } catch (AbstractException $e) {
                return new Response(500);
            }
        } else {
            // absence of a request body indicates an error
            return new Response(400, self::FATAL_ERR);
        }
    }

    protected function resumeSession($id): bool {
        try {
            // verify the supplied session is valid
            $s = Arsse::$db->sessionResume((string) $id);
        } catch (\JKingWeb\Arsse\User\ExceptionSession $e) {
            // if not throw an exception
            throw new Exception("NOT_LOGGED_IN");
        }
        // resume the session (currently only the user name)
        Arsse::$user->id = $s['user'];
        return true;
    }

    public function opGetApiLevel(array $data): array {
        return ['level' => self::LEVEL];
    }
    
    public function opGetVersion(array $data): array {
        return [
            'version'       => self::VERSION,
            'arsse_version' => \JKingWeb\Arsse\VERSION,
        ];
    }

    public function opLogin(array $data): array {
        if (isset($data['user']) && isset($data['password']) && Arsse::$user->auth($data['user'], $data['password'])) {
            $id = Arsse::$db->sessionCreate($data['user']);
            return [
                'session_id' => $id,
                'api_level'  => self::LEVEL
            ];
        } else {
            throw new Exception("LOGIN_ERROR");
        }
    }

    public function opLogout(array $data): array {
        Arsse::$db->sessionDestroy(Arsse::$user->id, $data['sid']);
        return ['status' => "OK"];
    }

    public function opIsLoggedIn(array $data): array {
        // session validity is already checked by the dispatcher, so we need only return true
        return ['status' => true];
    }

    public function opAddCategory(array $data) {
        $in = [
            'name'   => isset($data['caption']) ? $data['caption'] : "",
            'parent' => isset($data['parent_id']) ? $data['parent_id'] : null,
        ];
        if (!$in['parent']) {
            $in['parent'] = null;
        }
        try {
            return Arsse::$db->folderAdd(Arsse::$user->id, $in);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10236: // folder already exists
                    // retrieve the ID of the existing folder; duplicating a folder silently returns the existing one
                    $folders = Arsse::$db->folderList(Arsse::$user->id, $in['parent'], false);
                    foreach ($folders as $folder) {
                        if ($folder['name']==$in['name']) {
                            return (int) $folder['id'];
                        }
                    }
                    return false;
                case 10235: // parent folder does not exist; this returns false as an ID
                    return false;
                default: // other errors related to input
                    throw new Exception("INCORRECT_USAGE");
            }
        }
    }

    public function opRemoveCategory(array $data) {
        if (!isset($data['category_id']) || !ValueInfo::id($data['category_id'])) {
            // if the folder is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        try {
            // attempt to remove the folder
            Arsse::$db->folderRemove(Arsse::$user->id, (int) $data['category_id']);
        } catch(ExceptionInput $e) {
            // ignore all errors
        }
        return null;
    }

    public function opMoveCategory(array $data) {
        if (!isset($data['category_id']) || !ValueInfo::id($data['category_id']) || !isset($data['parent_id']) || !ValueInfo::id($data['parent_id'], true)) {
            // if the folder or parent is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        $in = [
            'parent' => (int) $data['parent_id'],
        ];
        try {
            // try to move the folder
            Arsse::$db->folderPropertiesSet(Arsse::$user->id, (int) $data['category_id'], $in);
        } catch(ExceptionInput $e) {
            // ignore all errors
        }
        return null;
    }

    public function opRenameCategory(array $data) {
        if (!isset($data['category_id']) || !ValueInfo::id($data['category_id']) || !isset($data['caption'])) {
            // if the folder is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        $info = ValueInfo::str($data['caption']);
        if (!($info & ValueInfo::VALID) || ($info & ValueInfo::EMPTY) || ($info & ValueInfo::WHITE)) {
            // if the folder name is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        $in = [
            'name' => (string) $data['caption'],
        ];
        try {
            // try to rename the folder
            Arsse::$db->folderPropertiesSet(Arsse::$user->id, (int) $data['category_id'], $in);
        } catch(ExceptionInput $e) {
            // ignore all errors
        }
        return null;
    }

    public function opUnsubscribeFeed(array $data): array {
        if (!isset($data['feed_id']) || !ValueInfo::id($data['feed_id'])) {
            // if the feed is invalid, throw an error
            throw new Exception("FEED_NOT_FOUND");
        }
        try {
            // attempt to remove the feed
            Arsse::$db->subscriptionRemove(Arsse::$user->id, (int) $data['feed_id']);
        } catch(ExceptionInput $e) {
            throw new Exception("FEED_NOT_FOUND");
        }
        return ['status' => "OK"];
    }

    public function opMoveFeed(array $data) {
        if (!isset($data['feed_id']) || !ValueInfo::id($data['feed_id']) || !isset($data['category_id']) || !ValueInfo::id($data['category_id'], true)) {
            // if the feed or folder is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        $in = [
            'folder' => (int) $data['category_id'],
        ];
        try {
            // try to move the feed
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, (int) $data['feed_id'], $in);
        } catch(ExceptionInput $e) {
            // ignore all errors
        }
        return null;
    }

    public function opRenameFeed(array $data) {
        if (!isset($data['feed_id']) || !ValueInfo::id($data['feed_id']) || !isset($data['caption'])) {
            // if the feed is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        $info = ValueInfo::str($data['caption']);
        if (!($info & ValueInfo::VALID) || ($info & ValueInfo::EMPTY) || ($info & ValueInfo::WHITE)) {
            // if the feed name is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        $in = [
            'name' => (string) $data['caption'],
        ];
        try {
            // try to rename the feed
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, (int) $data['feed_id'], $in);
        } catch(ExceptionInput $e) {
            // ignore all errors
        }
        return null;
    }
}
