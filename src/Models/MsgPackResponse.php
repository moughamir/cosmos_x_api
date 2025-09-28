<?php
// src/Utils/MsgPackResponse.php
namespace App\Models;

use Psr\Http\Message\ResponseInterface as Response;

class MsgPackResponse {
    public static function withMsgPack(Response $response, array $data): Response {
        if (!extension_loaded('msgpack')) {
            throw new \Exception("MsgPack extension not enabled.");
        }
        $response->getBody()->write(msgpack_pack($data));
        return $response->withHeader('Content-Type', 'application/x-msgpack');
    }
}
