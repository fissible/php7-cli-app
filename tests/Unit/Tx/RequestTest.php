<?php declare(strict_types=1);

namespace Tests\Unit\Tx;

use PhpCli\Tx\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function testDelete()
    {
        $Request = new Request('https://example.com/items/2');
        $Request->setTest([
            'result' => 'ok',
            'error' => '',
            'info' => ['http_code' => 200]
        ]);
        $Response = $Request->delete();

        $this->assertEquals('ok', $Response->body);
        $this->assertEquals(200, $Response->code);
        $this->assertEquals('OK', $Response->message);
    }
    
    public function testGet()
    {
        $result = '{"title":"The Third"}';
        $Request = new Request('https://example.com/items/3');
        $Request->setTest([
            'result' => $result,
            'error' => '',
            'info' => ['http_code' => 200]
        ]);
        $Response = $Request->get();

        $this->assertEquals($result, $Response->body);
        $this->assertEquals(200, $Response->code);
        $this->assertEquals('OK', $Response->message);
    }
    
    public function testPatch()
    {
        $result = '{"length":14}';
        $Request = new Request('https://example.com/items/3');
        $Request->setTest([
            'result' => $result,
            'error' => '',
            'info' => ['http_code' => 200]
        ]);
        $Response = $Request->patch([
            'length' => 14
        ]);

        $this->assertEquals($result, $Response->body);
        $this->assertEquals(200, $Response->code);
        $this->assertEquals('OK', $Response->message);
    }
    
    public function testPost()
    {
        $result = '{"title":"The Fourth","length":13}';
        $Request = new Request('https://example.com/items');
        $Request->setTest([
            'result' => $result,
            'error' => '',
            'info' => ['http_code' => 200]
        ]);
        $Response = $Request->post([
            'title' => 'The Fourth',
            'length' => 13
        ]);

        $this->assertEquals($result, $Response->body);
        $this->assertEquals(200, $Response->code);
        $this->assertEquals('OK', $Response->message);
    }
    
    public function testPut()
    {
        $result = '{"title":"The Fifth","length":12}';
        $Request = new Request('https://example.com/items');
        $Request->setTest([
            'result' => $result,
            'error' => '',
            'info' => ['http_code' => 200]
        ]);
        $Response = $Request->put([
            'title' => 'The Fifth',
            'length' => 12
        ]);

        $this->assertEquals($result, $Response->body);
        $this->assertEquals(200, $Response->code);
        $this->assertEquals('OK', $Response->message);
    }
}