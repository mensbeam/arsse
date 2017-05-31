<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use PicoFeed\Reader\Reader;
use PicoFeed\PicoFeedException;
use PicoFeed\Reader\Favicon;
use PicoFeed\Config\Config;

class Feed {
    public $data = null;
    public $favicon;
    public $parser;
    public $reader;
    public $resource;
    public $modified = false;
    public $lastModified;
    public $nextFetch;
    public $newItems = [];
    public $changedItems = [];

    public function __construct(int $feedID = null, string $url, string $lastModified = '', string $etag = '', string $username = '', string $password = '') {
        // fetch the feed
        $this->download($url, $lastModified, $etag, $username, $password);
        // format the HTTP Last-Modified date returned
        $lastMod = $this->resource->getLastModified();
        if(strlen($lastMod)) {
            $this->lastModified = \DateTime::createFromFormat("!D, d M Y H:i:s e", $lastMod);
        }
        $this->modified = $this->resource->isModified();
        //parse the feed, if it has been modified
        if($this->modified) {
            $this->parse();
            // ascertain whether there are any articles not in the database
            $this->matchToDatabase($feedID);
            // if caching header fields are not sent by the server, try to ascertain a last-modified date from the feed contents
            if(!$this->lastModified) $this->lastModified = $this->computeLastModified();
            // we only really care if articles have been modified; if there are no new articles, act as if the feed is unchanged
            if(!sizeof($this->newItems) && !sizeof($this->changedItems)) $this->modified = false;
        }
        // compute the time at which the feed should next be fetched
        $this->nextFetch = $this->computeNextFetch();
    }

    public function download(string $url, string $lastModified = '', string $etag = '', string $username = '', string $password = ''): bool {
        try {
            $config = new Config;
            $config->setMaxBodySize(Data::$conf->fetchSizeLimit);
            $config->setClientTimeout(Data::$conf->fetchTimeout);
            $config->setGrabberTimeout(Data::$conf->fetchTimeout);
            $config->setClientUserAgent(Data::$conf->fetchUserAgentString);
            $config->setGrabberUserAgent(Data::$conf->fetchUserAgentString);

            $this->reader = new Reader($config);
            $this->resource = $this->reader->download($url, $lastModified, $etag, $username, $password);
        } catch (PicoFeedException $e) {
            throw new Feed\Exception($url, $e);
        }
        return true;
    }

    public function parse(): bool {
        try {
            $this->parser = $this->reader->getParser(
                $this->resource->getUrl(),
                $this->resource->getContent(),
                $this->resource->getEncoding()
            );
            $feed = $this->parser->execute();

            // Grab the favicon for the feed; returns an empty string if it cannot find one.
            // Some feeds might use a different domain (eg: feedburner), so the site url is
            // used instead of the feed's url.
            $this->favicon = (new Favicon)->find($feed->siteUrl);
        } catch (PicoFeedException $e) {
            throw new Feed\Exception($this->resource->getUrl(), $e);
        }

        // PicoFeed does not provide valid ids when there is no id element. Its solution
        // of hashing the url, title, and content together for the id if there is no id
        // element is stupid. Many feeds are frankenstein mixtures of Atom and RSS, but
        // some are pure RSS with guid elements while others use the Dublin Core spec for
        // identification. These feeds shouldn't be duplicated when updated. That should
        // only be reserved for severely broken feeds.

        foreach ($feed->items as $f) {
            // Hashes used for comparison to check for updates and also to identify when an
            // id doesn't exist.
            $content = $f->content.$f->enclosureUrl.$f->enclosureType;
            // if the item link URL and item title are both equal to the feed link URL, then the item has neither a link URL nor a title
            if($f->url==$feed->siteUrl && $f->title==$feed->siteUrl) {
                $f->urlTitleHash = "";
            } else {
                $f->urlTitleHash = hash('sha256', $f->url.$f->title);
            }
            // if the item link URL is equal to the feed link URL, it has no link URL; if there is additionally no content, these should not be hashed
            if(!strlen($content) && $f->url==$feed->siteUrl) {
               $f->urlContentHash = ""; 
            } else {
                $f->urlContentHash = hash('sha256', $f->url.$content);
            }
            // if the item's title is the same as its link URL, it has no title; if there is additionally no content, these should not be hashed
            if(!strlen($content) && $f->title==$f->url) {
                $f->titleContentHash = "";
            } else {
                $f->titleContentHash = hash('sha256', $f->title.$content);
            }

            // If there is an Atom id element use it as the id.
            $id = (string)$f->xml->children('http://www.w3.org/2005/Atom')->id;
            if ($id !== '') {
                $f->id = hash('sha256', $id);
                continue;
            }

            // If there is a guid element use it as the id.
            $id = (string)$f->xml->guid;
            if ($id !== '') {
                $f->id = hash('sha256', $id);
                continue;
            }

            // If there is a Dublin Core identifier use it.
            $id = (string)$f->xml->children('http://purl.org/dc/elements/1.1/')->identifier;
            if ($id !== '') {
                $f->id = hash('sha256', $id);
                continue;
            }

            // If there aren't any of those there is no id.
            $f->id = null;
        }
        $this->data = $feed;
        return true;
    }

    protected function deduplicateItems(array $items): array {
        /* Rationale:
            Some newsfeeds (notably Planet) include multiple versions of an 
            item if it is updated. As we only care about the latest, we
            try to remove any "old" versions of an item that might also be 
            present within the feed.
        */
        $out = [];
        foreach($items as $item) {
            foreach($out as $index => $check) {
                // if the two items both have IDs and they differ, they do not match, regardless of hashes
                if($item->id && $check->id && $item->id != $check->id) continue;
                // if the two items have the same ID or any one hash matches, they are two versions of the same item
                if(
                    ($item->id && $check->id && $item->id == $check->id) ||
                    ($item->urlTitleHash     && $item->urlTitleHash     == $check->urlTitleHash)      ||
                    ($item->urlContentHash   && $item->urlContentHash   == $check->urlContentHash)    ||
                    ($item->titleContentHash && $item->titleContentHash == $check->titleContentHash)
                ) {
                    if(// because newsfeeds are usually order newest-first, the later item should only be used if...
                        // the later item has an update date and the existing item does not
                        ($item->updatedDate && !$check->updatedDate) ||
                        // the later item has an update date newer than the existing item's
                        ($item->updatedDate && $check->updatedDate && $item->updatedDate->getTimestamp() > $check->updatedDate->getTimestamp()) ||
                        // neither item has update dates, both have publish dates, and the later item has a newer publish date
                        (!$item->updatedDate && !$check->updatedDate && $item->publishedDate && $check->publishedDate && $item->publishedDate->getTimestamp() > $check->publishedDate->getTimestamp())
                    ) {
                        // if the later item should be used, replace the existing one
                        $out[$index] = $item;
                        continue 2;
                    } else {
                        // otherwise skip the item
                        continue 2;
                    }
                }
            }
            // if there was no match, add the item
            $out[] = $item;
        }
        return $out;
    }

    public function matchToDatabase(int $feedID = null): bool {
        // first perform deduplication on items
        $items = $this->deduplicateItems($this->data->items);
        // if we haven't been given a database feed ID to check against, all items are new
        if(is_null($feedID)) {
            $this->newItems = $items;
            return true;
        }
        // get as many of the latest articles in the database as there are in the feed
        $articles = Data::$db->feedMatchLatest($feedID, sizeof($items))->getAll();
        // perform a first pass matching the latest articles against items in the feed
        list($this->newItems, $this->changedItems) = $this->matchItems($items, $articles);
        if(sizeof($this->newItems) && sizeof($items) <= sizeof($articles)) {
            // if we need to, perform a second pass on the database looking specifically for IDs and hashes of the new items
            $ids = $hashesUT = $hashesUC = $hashesTC = [];
            foreach($this->newItems as $i) {
                if($i->id)               $ids[]      = $i->id;
                if($i->urlTitleHash)     $hashesUT[] = $i->urlTitleHash;
                if($i->urlContentHash)   $hashesUC[] = $i->urlContentHash;
                if($i->titleContentHash) $hashesTC[] = $i->titleContentHash;
            }
            $articles = Data::$db->feedMatchIds($feedID, $ids, $hashesUT, $hashesUC, $hashesTC)->getAll();
            list($this->newItems, $changed) = $this->matchItems($this->newItems, $articles);
            $this->changedItems = array_merge($this->changedItems, $changed);
        }
        // TODO: fetch full content when appropriate
        return true;
    }

    public function matchItems(array $items, array $articles): array {
        $new =  $edited = [];
        // iterate through the articles and for each determine whether it is existing, edited, or entirely new
        foreach($items as $i) {
            $found = false;
            foreach($articles as $a) {
                // if the item has an ID and it doesn't match the article ID, the two don't match, regardless of hashes
                if($i->id && $i->id !== $a['guid']) continue;
                if(
                    // the item matches if the GUID matches...
                    ($i->id && $i->id === $a['guid']) ||
                    // ... or if any one of the hashes match
                    ($i->urlTitleHash     && $i->urlTitleHash     === $a['url_title_hash'])     ||
                    ($i->urlContentHash   && $i->urlContentHash   === $a['url_content_hash'])   ||
                    ($i->titleContentHash && $i->titleContentHash === $a['title_content_hash'])
                ) {
                    if($i->updatedDate && $i->updatedDate->getTimestamp() !== $a['edited_date']) {
                        // if the item has an edit timestamp and it doesn't match that of the article in the database, the the article has been edited
                        // we store the item index and database record ID as a key/value pair
                        $found = true;
                        $edited[$a['id']] = $i;
                        break;
                    } else if($i->urlTitleHash !== $a['url_title_hash'] || $i->urlContentHash !== $a['url_content_hash'] || $i->titleContentHash !== $a['title_content_hash']) {
                        // if any of the hashes do not match, then the article has been edited
                        $found = true;
                        $edited[$a['id']] = $i;
                        break;
                    } else {
                        // otherwise the item is unchanged and we can ignore it
                        $found = true;
                        break;
                    }
                }
            }
            if(!$found) $new[] = $i;
        }
        return [$new, $edited];
    }

    public function computeNextFetch(): \DateTime {
        $now = new \DateTime();
        if(!$this->modified) {
            $diff = $now->getTimestamp() - $this->lastModified->getTimestamp();
            $offset = $this->normalizeDateDiff($diff);
            $now->modify("+".$offset);
        } else {
            // the algorithm for updated feeds (returning 200 rather than 304) uses the same parameters as for 304,
            // save that the last three intervals between item dates are computed, and if any two fall within
            // the same interval range, that interval is used (e.g. if the intervals are 23m, 12m, and 4h, the used
            // interval is "less than 30m"). If there is no commonality, the feed is checked in 1 hour.
            $offsets = [];
            $dates = $this->gatherDates();
            if(sizeof($dates) > 3) {
                for($a = 0; $a < 3; $a++) {
                    $diff = $dates[$a] - $dates[$a+1];
                    $offsets[] = $this->normalizeDateDiff($diff);
                }
                if($offsets[0]==$offsets[1] || $offsets[0]==$offsets[2]) {
                    $now->modify("+".$offsets[0]);
                } else if($offsets[1]==$offsets[2]) {
                    $now->modify("+".$offsets[1]);
                } else {
                    $now->modify("+ 1 hour");
                }
            } else {
                $now->modify("+ 1 hour");
            }
        }
        return $now;
    }

    public static function nextFetchOnError($errCount): \DateTime {
        if($errCount < 3) {
            $offset = "5 minutes";
        } else if($errCount < 15) {
            $offset = "3 hours";
        } else {
            $offset = "1 day";
        }
        return new \DateTime("now + ".$offset);
    }

    protected function normalizeDateDiff(int $diff): string {
        if($diff < (30 * 60)) { // less than 30 minutes
            $offset = "15 minutes";
        } else if($diff < (60 * 60)) { // less than an hour
            $offset = "30 minutes";
        } else if($diff < (3 * 60 * 60)) { // less than three hours
            $offset = "1 hour";
        } else if($diff >= (36 * 60 * 60)) { // more than 36 hours
            $offset = "1 day";
        } else {
            $offset = "3 hours";
        }
        return $offset;
    }

    public function computeLastModified() {
        if(!$this->modified) {
            return $this->lastModified;
        } else {
            $dates = $this->gatherDates();
        }
        if(sizeof($dates)) {
            $now = new \DateTime();
            $now->setTimestamp($dates[0]);
            return $now;
        } else {
            return null;
        }
    }

    protected function gatherDates(): array {
        $dates = [];
        foreach($this->data->items as $item) {
            if($item->updatedDate) $dates[] = $item->updatedDate->getTimestamp();
            if($item->publishedDate) $dates[] = $item->publishedDate->getTimestamp();
        }
        $dates = array_unique($dates, \SORT_NUMERIC);
        rsort($dates);
        return $dates;
    }
}