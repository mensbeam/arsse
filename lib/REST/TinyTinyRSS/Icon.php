<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\TinyTinyRSS;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\ExceptionInput;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\EmptyResponse as Response;

class Icon extends \JKingWeb\Arsse\REST\AbstractHandler {
    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        if ($req->getAttribute("authenticated", false)) {
            // if HTTP authentication was successfully used, set the expected user ID
            Arsse::$user->id = $req->getAttribute("authenticatedUser");
        } elseif ($req->getAttribute("authenticationFailed", false) || Arsse::$conf->userHTTPAuthRequired) {
            // otherwise if HTTP authentication failed or did not occur when it is required, deny access at the HTTP level
            return new Response(401);
        }
        if ($req->getMethod() !== "GET") {
            // only GET requests are allowed
            return new Response(405, ['Allow' => "GET"]);
        } elseif (!preg_match("<^(\d+)\.ico$>", $req->getRequestTarget(), $match) || !((int) $match[1])) {
            return new Response(404);
        }
        try {
            $url = Arsse::$db->subscriptionIcon(Arsse::$user->id ?? null, (int) $match[1], false)['url'] ?? null;
            if (!$url) {
                return new Response(404);
            }
            if (($pos = strpos($url, "\r")) !== false || ($pos = strpos($url, "\n")) !== false) {
                $url = substr($url, 0, $pos);
            }
            return new Response(301, ['Location' => $url]);
        } catch (ExceptionInput $e) {
            return new Response(404);
        }
    }
}
