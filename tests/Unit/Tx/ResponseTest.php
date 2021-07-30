<?php declare(strict_types=1);

namespace Tests\Unit\Tx;

use PhpCli\Tx\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testResponse()
    {
        $R = new Response('ok');

        $this->assertEquals('ok', $R->body);
        $this->assertEquals(200, $R->code);
        $this->assertEquals('OK', $R->message);

        $item = new \stdClass();
        $item->id = 14;
        $item->title = 'The Item Title';
        $response = sprintf('{"id":%d,"title":"%s"}', $item->id, $item->title);
        $R = new Response($response, [
            'Content-Type' => 'application/json'
        ], [
            'http_code' => 200,
            'request_header' => 'Content-Type: application/json'
        ]);

        $this->assertEquals($response, $R->raw);
        $this->assertEquals($item, json_decode($R->body));
        $this->assertEquals(200, $R->code);
        $this->assertEquals('OK', $R->message);

        $headers = implode("\n", ['Accept: text/xml,application/xml', 'Keep-Alive: 300']);
        $R = new Response('', [], [
            'http_code' => 301,
            'request_header' => $headers
        ]);
        $info = $R->info;

        $this->assertEquals('', $R->body);
        $this->assertEquals(301, $R->code);
        $this->assertEquals('Moved Permanently', $R->message);
        $this->assertEquals($headers, $info['request_header']);

        $R = new Response('', [], ['http_code' => 404]);

        $this->assertEquals('', $R->body);
        $this->assertEquals(404, $R->code);
        $this->assertEquals('Not Found', $R->message);

        $R = new Response('', [], ['http_code' => 500]);

        $this->assertEquals('', $R->body);
        $this->assertEquals(500, $R->code);
        $this->assertEquals('Internal Server Error', $R->message);
    }
}