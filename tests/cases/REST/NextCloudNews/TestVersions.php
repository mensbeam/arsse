<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\NextCloudNews;

use JKingWeb\Arsse\REST\NextCloudNews\Versions;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response\JsonResponse as Response;
use Zend\Diactoros\Response\EmptyResponse;

/** @covers \JKingWeb\Arsse\REST\NextCloudNews\Versions */
class TestVersions extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp() {
        self::clearData();
    }

    protected function req(string $method, string $target): ResponseInterface {
        $prefix = "/index.php/apps/news/api";
        $url = $prefix.$target;
        $req = $this->serverRequest($method, $url, $prefix);
        return (new Versions)->dispatch($req);
    }

    public function testFetchVersionList() {
        $exp = new Response(['apiLevels' => ['v1-2']]);
        $this->assertMessage($exp, $this->req("GET", "/"));
        $this->assertMessage($exp, $this->req("GET", "/"));
        $this->assertMessage($exp, $this->req("GET", "/"));
    }

    public function testRespondToOptionsRequest() {
        $exp = new EmptyResponse(204, ['Allow' => "HEAD,GET"]);
        $this->assertMessage($exp, $this->req("OPTIONS", "/"));
    }

    public function testUseIncorrectMethod() {
        $exp = new EmptyResponse(405, ['Allow' => "HEAD,GET"]);
        $this->assertMessage($exp, $this->req("POST", "/"));
    }

    public function testUseIncorrectPath() {
        $exp = new EmptyResponse(404);
        $this->assertMessage($exp, $this->req("GET", "/ook"));
        $this->assertMessage($exp, $this->req("OPTIONS", "/ook"));
    }
}
