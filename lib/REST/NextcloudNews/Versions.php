<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextcloudNews;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\JsonResponse as Response;
use Laminas\Diactoros\Response\EmptyResponse;

class Versions implements \JKingWeb\Arsse\REST\Handler {
    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        if (!preg_match("<^/?$>", $req->getRequestTarget())) {
            // if the request path is more than an empty string or a slash, the client is probably trying a version we don't support
            return new EmptyResponse(404);
        }
        switch ($req->getMethod()) {
            case "OPTIONS":
                // if the request method is OPTIONS, respond accordingly
                return new EmptyResponse(204, ['Allow' => "HEAD,GET"]);
            case "GET":
                // otherwise return the supported versions
                $out = [
                    'apiLevels' => [
                        'v1-2',
                    ],
                ];
                return new Response($out);
            default:
                // if any other method was used, this is an error
                return new EmptyResponse(405, ['Allow' => "HEAD,GET"]);
        }
    }
}
