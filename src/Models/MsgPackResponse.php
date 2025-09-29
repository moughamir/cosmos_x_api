<?php

namespace App\Models;

use Psr\Http\Message\ResponseInterface as Response;

class MsgPackResponse
{
    public static function withMsgPack(Response $response, array $data): Response
    {
        if (!extension_loaded('msgpack')) {
            // Fallback to JSON if msgpack extension is not available
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(msgpack_pack($data));
        return $response->withHeader('Content-Type', 'application/x-msgpack');
    }
}
