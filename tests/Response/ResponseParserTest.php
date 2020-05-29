<?php

use JsonRPC\Response\ResponseParser;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../vendor/autoload.php';

class ResponseParserTest extends TestCase
{
    public function testSingleRequest()
    {
        $result = ResponseParser::create()
            ->parse(json_decode('{"jsonrpc": "2.0", "result": "foobar", "id": "1"}', true));

        $this->assertEquals('foobar', $result);
    }

    public function testWithBadJsonFormat()
    {
        $this->expectException('\JsonRPC\Exception\InvalidJsonFormatException');

        ResponseParser::create()
            ->parse('foobar');
    }

    public function testWithBadProcedure()
    {
        $this->expectException('BadFunctionCallException');

        ResponseParser::create()
            ->parse(json_decode('{"jsonrpc": "2.0", "error": {"code": -32601, "message": "Method not found"}, "id": "1"}', true));
    }

    public function testWithInvalidArgs()
    {
        $this->expectException('InvalidArgumentException');

        ResponseParser::create()
            ->parse(json_decode('{"jsonrpc": "2.0", "error": {"code": -32602, "message": "Invalid params"}, "id": "1"}', true));
    }

    public function testWithInvalidRequest()
    {
        $this->expectException('\JsonRPC\Exception\InvalidJsonRpcFormatException');

        ResponseParser::create()
            ->parse(json_decode('{"jsonrpc": "2.0", "error": {"code": -32600, "message": "Invalid Request"}, "id": null}', true));
    }

    public function testWithParseError()
    {
        $this->expectException('\JsonRPC\Exception\InvalidJsonFormatException');

        ResponseParser::create()
            ->parse(json_decode('{"jsonrpc": "2.0", "error": {"code": -32700, "message": "Parse error"}, "id": null}', true));
    }

    public function testWithOtherError()
    {
        $this->expectException('\JsonRPC\Exception\ResponseException');

        ResponseParser::create()
            ->parse(json_decode('{"jsonrpc": "2.0", "error": {"code": 42, "message": "Something", "data": "foobar"}, "id": null}', true));
    }

    public function testBatch()
    {
        $payload = '[
            {"jsonrpc": "2.0", "result": 7, "id": "1"},
            {"jsonrpc": "2.0", "result": 19, "id": "2"}
        ]';

        $result = ResponseParser::create()
            ->parse(json_decode($payload, true));

        $this->assertEquals(array(7, 19), $result);
    }

    public function testBatchWithError()
    {
        $payload = '[
            {"jsonrpc": "2.0", "result": 7, "id": "1"},
            {"jsonrpc": "2.0", "result": 19, "id": "2"},
            {"jsonrpc": "2.0", "error": {"code": -32602, "message": "Invalid params"}, "id": "1"}
        ]';

        $this->expectException('InvalidArgumentException');

        ResponseParser::create()
            ->parse(json_decode($payload, true));
    }
}
