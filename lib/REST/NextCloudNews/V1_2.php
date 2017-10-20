<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextCloudNews;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\Misc\Context;
use JKingWeb\Arsse\Misc\ValueInfo;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\REST\Response;
use JKingWeb\Arsse\REST\Exception501;
use JKingWeb\Arsse\REST\Exception405;

class V1_2 extends \JKingWeb\Arsse\REST\AbstractHandler {
    const REALM = "NextCloud News API v1-2";
    const VERSION = "11.0.5";

    protected $dateFormat = "unix";

    protected $validInput = [
        'name'         => ValueInfo::T_STRING,
        'url'          => ValueInfo::T_STRING,
        'folderId'     => ValueInfo::T_INT,
        'feedTitle'    => ValueInfo::T_STRING,
        'userId'       => ValueInfo::T_STRING,
        'feedId'       => ValueInfo::T_INT,
        'newestItemId' => ValueInfo::T_INT,
        'batchSize'    => ValueInfo::T_INT,
        'offset'       => ValueInfo::T_INT,
        'type'         => ValueInfo::T_INT,
        'id'           => ValueInfo::T_INT,
        'getRead'      => ValueInfo::T_BOOL,
        'oldestFirst'  => ValueInfo::T_BOOL,
        'lastModified' => ValueInfo::T_DATE,
        'items'        => ValueInfo::T_MIXED | ValueInfo::M_ARRAY,
    ];
    
    public function __construct() {
    }

    public function dispatch(\JKingWeb\Arsse\REST\Request $req): Response {
        // try to authenticate
        if (!Arsse::$user->authHTTP()) {
            return new Response(401, "", "", ['WWW-Authenticate: Basic realm="'.self::REALM.'"']);
        }
        // normalize the input
        if ($req->body) {
            // if the entity body is not JSON according to content type, return "415 Unsupported Media Type"
            if (!preg_match("<^application/json\b|^$>", $req->type)) {
                return new Response(415, "", "", ['Accept: application/json']);
            }
            $data = @json_decode($req->body, true);
            if (json_last_error() != \JSON_ERROR_NONE) {
                // if the body could not be parsed as JSON, return "400 Bad Request"
                return new Response(400);
            }
        } else {
            $data = [];
        }
        // FIXME: Do query parameters take precedence in NextCloud? Is there a conflict error when values differ?
        $data = $this->normalizeInput(array_merge($data, $req->query), $this->validInput, "unix");
        // check to make sure the requested function is implemented
        try {
            $func = $this->chooseCall($req->paths, $req->method);
        } catch (Exception501 $e) {
            return new Response(501);
        } catch (Exception405 $e) {
            return new Response(405, "", "", ["Allow: ".$e->getMessage()]);
        }
        if (!method_exists($this, $func)) {
            return new Response(501); // @codeCoverageIgnore
        }
        // dispatch
        try {
            return $this->$func($req->paths, $data);
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            // if there was a REST exception return 400
            return new Response(400);
        } catch (AbstractException $e) {
            // if there was any other Arsse exception return 500
            return new Response(500);
        }
        // @codeCoverageIgnoreEnd
    }

    protected function chooseCall(array $url, string $method): string {
        $choices = [
            'items' => [],
            'folders' => [
                ''       => ['GET' => "folderList",   'POST'   => "folderAdd"],
                '1'      => ['PUT' => "folderRename", 'DELETE' => "folderRemove"],
                '1/read' => ['PUT' => "folderMarkRead"],
            ],
            'feeds' => [
                ''         => ['GET' => "subscriptionList", 'POST' => "subscriptionAdd"],
                '1'        => ['DELETE' => "subscriptionRemove"],
                '1/move'   => ['PUT' => "subscriptionMove"],
                '1/rename' => ['PUT' => "subscriptionRename"],
                '1/read'   => ['PUT' => "subscriptionMarkRead"],
                'all'      => ['GET' => "feedListStale"],
                'update'   => ['GET' => "feedUpdate"],
            ],
            'items' => [
                ''                => ['GET' => "articleList"],
                'updated'         => ['GET' => "articleList"],
                'read'            => ['PUT' => "articleMarkReadAll"],
                '1/read'          => ['PUT' => "articleMarkRead"],
                '1/unread'        => ['PUT' => "articleMarkRead"],
                'read/multiple'   => ['PUT' => "articleMarkReadMulti"],
                'unread/multiple' => ['PUT' => "articleMarkReadMulti"],
                '1/1/star'        => ['PUT' => "articleMarkStarred"],
                '1/1/unstar'      => ['PUT' => "articleMarkStarred"],
                'star/multiple'   => ['PUT' => "articleMarkStarredMulti"],
                'unstar/multiple' => ['PUT' => "articleMarkStarredMulti"],
            ],
            'cleanup' => [
                'before-update' => ['GET' => "cleanupBefore"],
                'after-update'  => ['GET' => "cleanupAfter"],
            ],
            'version' => [
                '' => ['GET' => "serverVersion"],
            ],
            'status' => [
                '' => ['GET' => "serverStatus"],
            ],
            'user' => [
                '' => ['GET' => "userStatus"],
            ],
        ];
        // the first path element is the overall scope of the request
        $scope = $url[0];
        // any URL components which are database IDs (integers greater than zero) should be replaced with "1", for easier comparison (we don't care about the specific ID)
        for ($a = 0; $a < sizeof($url); $a++) {
            if (ValueInfo::id($url[$a])) {
                $url[$a] = "1";
            }
        }
        // normalize the HTTP method to uppercase
        $method = strtoupper($method);
        // if the scope is not supported, return 501
        if (!array_key_exists($scope, $choices)) {
            throw new Exception501();
        }
        // we now evaluate the supplied URL against every supported path for the selected scope
        // the URL is evaluated as an array so as to avoid decoded escapes turning invalid URLs into valid ones
        foreach ($choices[$scope] as $path => $funcs) {
            // add the scope to the path to match against and split it
            $path = (string) $path;
            $path = (strlen($path)) ? "$scope/$path" : $scope;
            $path = explode("/", $path);
            if ($path===$url) {
                // if the path matches, make sure the method is allowed
                if (array_key_exists($method, $funcs)) {
                    // if it is allowed, return the object method to run
                    return $funcs[$method];
                } else {
                    // otherwise return 405
                    throw new Exception405(implode(", ", array_keys($funcs)));
                }
            }
        }
        // if the path was not found, return 501
        throw new Exception501();
    }

    protected function feedTranslate(array $feed): array {
        // map fields to proper names
        $feed = $this->fieldMapNames($feed, [
            'id'               => "id",
            'url'              => "url",
            'title'            => "title",
            'added'            => "added",
            'pinned'           => "pinned",
            'link'             => "source",
            'faviconLink'      => "favicon",
            'folderId'         => "top_folder",
            'unreadCount'      => "unread",
            'ordering'         => "order_type",
            'updateErrorCount' => "err_count",
            'lastUpdateError'  => "err_msg",
        ]);
        // cast values
        $feed = $this->fieldMapTypes($feed, [
            'folderId' => "int",
            'pinned'   => "bool",
            'added'    => "datetime",
        ], $this->dateFormat);
        return $feed;
    }

    protected function articleTranslate(array $article) :array {
        // map fields to proper names
        $article = $this->fieldMapNames($article, [
            'id'            => "edition",
            'guid'          => "guid",
            'guidHash'      => "id",
            'url'           => "url",
            'title'         => "title",
            'author'        => "author",
            'pubDate'       => "edited_date",
            'body'          => "content",
            'enclosureMime' => "media_type",
            'enclosureLink' => "media_url",
            'feedId'        => "subscription",
            'unread'        => "unread",
            'starred'       => "starred",
            'lastModified'  => "modified_date",
            'fingerprint'   => "fingerprint",
        ]);
        // cast values
        $article = $this->fieldMapTypes($article, [
            'unread'       => "bool",
            'starred'      => "bool",
            'pubDate'      => "datetime",
            'lastModified' => "datetime",
        ], $this->dateFormat);
        return $article;
    }
    
    // list folders
    protected function folderList(array $url, array $data): Response {
        $folders = Arsse::$db->folderList(Arsse::$user->id, null, false)->getAll();
        return new Response(200, ['folders' => $folders]);
    }

    // create a folder
    protected function folderAdd(array $url, array $data): Response {
        try {
            $folder = Arsse::$db->folderAdd(Arsse::$user->id, ['name' => $data['name']]);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                // folder already exists
                case 10236: return new Response(409);
                // folder name not acceptable
                case 10231:
                case 10232: return new Response(422);
                // other errors related to input
                default: return new Response(400); // @codeCoverageIgnore
            }
        }
        $folder = Arsse::$db->folderPropertiesGet(Arsse::$user->id, $folder);
        return new Response(200, ['folders' => [$folder]]);
    }

    // delete a folder
    protected function folderRemove(array $url, array $data): Response {
        // perform the deletion
        try {
            Arsse::$db->folderRemove(Arsse::$user->id, (int) $url[1]);
        } catch (ExceptionInput $e) {
            // folder does not exist
            return new Response(404);
        }
        return new Response(204);
    }

    // rename a folder (also supports moving nesting folders, but this is not a feature of the API)
    protected function folderRename(array $url, array $data): Response {
        try {
            Arsse::$db->folderPropertiesSet(Arsse::$user->id, (int) $url[1], ['name' => $data['name']]);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                // folder does not exist
                case 10239: return new Response(404);
                // folder already exists
                case 10236: return new Response(409);
                // folder name not acceptable
                case 10231:
                case 10232: return new Response(422);
                // other errors related to input
                default: return new Response(400); // @codeCoverageIgnore
            }
        }
        return new Response(204);
    }

    // mark all articles associated with a folder as read
    protected function folderMarkRead(array $url, array $data): Response {
        if (!ValueInfo::id($data['newestItemId'])) {
            // if the item ID is invalid (i.e. not a positive integer), this is an error
            return new Response(422);
        }
        // build the context
        $c = new Context;
        $c->latestEdition((int) $data['newestItemId']);
        $c->folder((int) $url[1]);
        // perform the operation
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], $c);
        } catch (ExceptionInput $e) {
            // folder does not exist
            return new Response(404);
        }
        return new Response(204);
    }
    
    // return list of feeds which should be refreshed
    protected function feedListStale(array $url, array $data): Response {
        // function requires admin rights per spec
        if (Arsse::$user->rightsGet(Arsse::$user->id)==User::RIGHTS_NONE) {
            return new Response(403);
        }
        // list stale feeds which should be checked for updates
        $feeds = Arsse::$db->feedListStale();
        $out = [];
        foreach ($feeds as $feed) {
            // since in our implementation feeds don't belong the users, the 'userId' field will always be an empty string
            $out[] = ['id' => $feed, 'userId' => ""];
        }
        return new Response(200, ['feeds' => $out]);
    }
    
    // refresh a feed
    protected function feedUpdate(array $url, array $data): Response {
        // function requires admin rights per spec
        if (Arsse::$user->rightsGet(Arsse::$user->id)==User::RIGHTS_NONE) {
            return new Response(403);
        }
        try {
            Arsse::$db->feedUpdate($data['feedId']);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10239: // feed does not exist
                    return new Response(404);
                case 10237: // feed ID invalid
                    return new Response(422);
                default: // other errors related to input
                    return new Response(400); // @codeCoverageIgnore
            }
        }
        return new Response(204);
    }

    // add a new feed
    protected function subscriptionAdd(array $url, array $data): Response {
        // try to add the feed
        $tr = Arsse::$db->begin();
        try {
            $id = Arsse::$db->subscriptionAdd(Arsse::$user->id, (string) $data['url']);
        } catch (ExceptionInput $e) {
            // feed already exists
            return new Response(409);
        } catch (FeedException $e) {
            // feed could not be retrieved
            return new Response(422);
        }
        // if a folder was specified, move the feed to the correct folder; silently ignore errors
        if ($data['folderId']) {
            try {
                Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, $id, ['folder' => $data['folderId']]);
            } catch (ExceptionInput $e) {
            }
        }
        $tr->commit();
        // fetch the feed's metadata and format it appropriately
        $feed = Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, $id);
        $feed = $this->feedTranslate($feed);
        $out = ['feeds' => [$feed]];
        $newest = Arsse::$db->editionLatest(Arsse::$user->id, (new Context)->subscription($id));
        if ($newest) {
            $out['newestItemId'] = $newest;
        }
        return new Response(200, $out);
    }
    
    // return list of feeds for the logged-in user
    protected function subscriptionList(array $url, array $data): Response {
        $subs = Arsse::$db->subscriptionList(Arsse::$user->id);
        $out = [];
        foreach ($subs as $sub) {
            $out[] = $this->feedTranslate($sub);
        }
        $out = ['feeds' => $out];
        $out['starredCount'] = Arsse::$db->articleStarredCount(Arsse::$user->id);
        $newest = Arsse::$db->editionLatest(Arsse::$user->id);
        if ($newest) {
            $out['newestItemId'] = $newest;
        }
        return new Response(200, $out);
    }

    // delete a feed
    protected function subscriptionRemove(array $url, array $data): Response {
        try {
            Arsse::$db->subscriptionRemove(Arsse::$user->id, (int) $url[1]);
        } catch (ExceptionInput $e) {
            // feed does not exist
            return new Response(404);
        }
        return new Response(204);
    }

    // rename a feed
    protected function subscriptionRename(array $url, array $data): Response {
        try {
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, (int) $url[1], ['title' => (string) $data['feedTitle']]);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                // subscription does not exist
                case 10239: return new Response(404);
                // name is invalid
                case 10231:
                case 10232: return new Response(422);
                // other errors related to input
                default: return new Response(400); // @codeCoverageIgnore
            }
        }
        return new Response(204);
    }

    // move a feed to a folder
    protected function subscriptionMove(array $url, array $data): Response {
        // if no folder is specified this is an error
        if (!isset($data['folderId'])) {
            return new Response(422);
        }
        // perform the move
        try {
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, (int) $url[1], ['folder' => $data['folderId']]);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10239: // subscription does not exist
                    return new Response(404);
                case 10235: // folder does not exist
                case 10237: // folder ID is invalid
                    return new Response(422);
                default: // other errors related to input
                    return new Response(400); // @codeCoverageIgnore
            }
        }
        return new Response(204);
    }

    // mark all articles associated with a subscription as read
    protected function subscriptionMarkRead(array $url, array $data): Response {
        if (!ValueInfo::id($data['newestItemId'])) {
            // if the item ID is invalid (i.e. not a positive integer), this is an error
            return new Response(422);
        }
        // build the context
        $c = new Context;
        $c->latestEdition((int) $data['newestItemId']);
        $c->subscription((int) $url[1]);
        // perform the operation
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], $c);
        } catch (ExceptionInput $e) {
            // subscription does not exist
            return new Response(404);
        }
        return new Response(204);
    }

    // list articles and their properties
    protected function articleList(array $url, array $data): Response {
        // set the context options supplied by the client
        $c = new Context;
        // set the batch size
        if ($data['batchSize'] > 0) {
            $c->limit($data['batchSize']);
        }
        // set the order of returned items
        if ($data['oldestFirst']) {
            $c->reverse(false);
        } else {
            $c->reverse(true);
        }
        // set the edition mark-off; the database uses an or-equal comparison for internal consistency, but the protocol does not, so we must adjust by one
        if ($data['offset'] > 0) {
            if ($c->reverse) {
                $c->latestEdition($data['offset'] - 1);
            } else {
                $c->oldestEdition($data['offset'] + 1);
            }
        }
        // set whether to only return unread
        if (!ValueInfo::bool($data['getRead'], true)) {
            $c->unread(true);
        }
        // if no type is specified assume 3 (All)
        $data['type'] = $data['type'] ?? 3;
        switch ($data['type']) {
            case 0: // feed
                if (isset($data['id'])) {
                    $c->subscription($data['id']);
                }
                break;
            case 1: // folder
                if (isset($data['id'])) {
                    $c->folder($data['id']);
                }
                break;
            case 2: // starred
                $c->starred(true);
                break;
            default: // @codeCoverageIgnore
                // return all items
        }
        // whether to return only updated items
        if ($data['lastModified']) {
            $c->modifiedSince($data['lastModified']);
        }
        // perform the fetch
        try {
            $items = Arsse::$db->articleList(Arsse::$user->id, $c);
        } catch (ExceptionInput $e) {
            // ID of subscription or folder is not valid
            return new Response(422);
        }
        $out = [];
        foreach ($items as $item) {
            $out[] = $this->articleTranslate($item);
        }
        $out = ['items' => $out];
        return new Response(200, $out);
    }

    // mark all articles as read
    protected function articleMarkReadAll(array $url, array $data): Response {
        if (!ValueInfo::id($data['newestItemId'])) {
            // if the item ID is invalid (i.e. not a positive integer), this is an error
            return new Response(422);
        }
        // build the context
        $c = new Context;
        $c->latestEdition((int) $data['newestItemId']);
        // perform the operation
        Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], $c);
        return new Response(204);
    }

    // mark a single article as read
    protected function articleMarkRead(array $url, array $data): Response {
        // initialize the matching context
        $c = new Context;
        $c->edition((int) $url[1]);
        // determine whether to mark read or unread
        $set = ($url[2]=="read");
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['read' => $set], $c);
        } catch (ExceptionInput $e) {
            // ID is not valid
            return new Response(404);
        }
        return new Response(204);
    }

    // mark a single article as read
    protected function articleMarkStarred(array $url, array $data): Response {
        // initialize the matching context
        $c = new Context;
        $c->article((int) $url[2]);
        // determine whether to mark read or unread
        $set = ($url[3]=="star");
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['starred' => $set], $c);
        } catch (ExceptionInput $e) {
            // ID is not valid
            return new Response(404);
        }
        return new Response(204);
    }

    // mark an array of articles as read
    protected function articleMarkReadMulti(array $url, array $data): Response {
        // determine whether to mark read or unread
        $set = ($url[1]=="read");
        // start a transaction and loop through the items
        $t = Arsse::$db->begin();
        $in = array_chunk($data['items'] ?? [], 50);
        for ($a = 0; $a < sizeof($in); $a++) {
            // initialize the matching context
            $c = new Context;
            $c->editions($in[$a]);
            try {
                Arsse::$db->articleMark(Arsse::$user->id, ['read' => $set], $c);
            } catch (ExceptionInput $e) {
            }
        }
        $t->commit();
        return new Response(204);
    }

    // mark an array of articles as starred
    protected function articleMarkStarredMulti(array $url, array $data): Response {
        // determine whether to mark starred or unstarred
        $set = ($url[1]=="star");
        // start a transaction and loop through the items
        $t = Arsse::$db->begin();
        $in = array_chunk(array_column($data['items'] ?? [], "guidHash"), 50);
        for ($a = 0; $a < sizeof($in); $a++) {
            // initialize the matching context
            $c = new Context;
            $c->articles($in[$a]);
            try {
                Arsse::$db->articleMark(Arsse::$user->id, ['starred' => $set], $c);
            } catch (ExceptionInput $e) {
            }
        }
        $t->commit();
        return new Response(204);
    }

    protected function userStatus(array $url, array $data): Response {
        $data = Arsse::$user->propertiesGet(Arsse::$user->id, true);
        // construct the avatar structure, if an image is available
        if (isset($data['avatar'])) {
            $avatar = [
                'data' => base64_encode($data['avatar']['data']),
                'mime' => $data['avatar']['type'],
            ];
        } else {
            $avatar = null;
        }
        // construct the rest of the structure
        $out = [
            'userId' => Arsse::$user->id,
            'displayName' => $data['name'] ?? Arsse::$user->id,
            'lastLoginTimestamp' => time(),
            'avatar' => $avatar,
        ];
        return new Response(200, $out);
    }

    protected function cleanupBefore(array $url, array $data): Response {
        // function requires admin rights per spec
        if (Arsse::$user->rightsGet(Arsse::$user->id)==User::RIGHTS_NONE) {
            return new Response(403);
        }
        Service::cleanupPre();
        return new Response(204);
    }

    protected function cleanupAfter(array $url, array $data): Response {
        // function requires admin rights per spec
        if (Arsse::$user->rightsGet(Arsse::$user->id)==User::RIGHTS_NONE) {
            return new Response(403);
        }
        Service::cleanupPost();
        return new Response(204);
    }

    // return the server version
    protected function serverVersion(array $url, array $data): Response {
        return new Response(200, [
            'version' => self::VERSION,
            'arsse_version' => Arsse::VERSION,
        ]);
    }

    protected function serverStatus(array $url, array $data): Response {
        return new Response(200, [
            'version' => self::VERSION,
            'arsse_version' => Arsse::VERSION,
            'warnings' => [
                'improperlyConfiguredCron' => !Service::hasCheckedIn(),
            ]
        ]);
    }
}
