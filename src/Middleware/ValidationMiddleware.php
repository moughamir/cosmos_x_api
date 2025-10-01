<?php

namespace App\Middleware;

use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class ValidationMiddleware implements MiddlewareInterface
{
    private array $rules;
    private string $dataSource;

    /**
     * @param array $rules Validation rules (field => RuleInterface[])
     * @param string $dataSource Where to get the data from (query, body, attributes, etc.)
     */
    public function __construct(array $rules, string $dataSource = 'parsedBody')
    {
        $this->rules = $rules;
        $this->dataSource = $dataSource;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $data = [];
        
        // Get data from the specified source
        switch ($this->dataSource) {
            case 'query':
                $data = $request->getQueryParams();
                break;
            case 'parsedBody':
                $data = (array) $request->getParsedBody();
                break;
            case 'attributes':
                $data = $request->getAttributes();
                break;
            case 'body':
                $body = (string) $request->getBody();
                $data = json_decode($body, true) ?: [];
                break;
            default:
                $method = 'get' . ucfirst($this->dataSource);
                if (method_exists($request, $method)) {
                    $data = (array) $request->$method();
                }
        }

        $validator = new Validator();

        // Add rules to the validator
        foreach ($this->rules as $field => $rules) {
            $validator->addRule($field, $rules);
        }

        // Validate the data
        if (!$validator->validate($data)) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'errors' => $validator->getErrors()
                ]
            ], JSON_PRETTY_PRINT));

            return $response
                ->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }

        // Add the validated data to the request attributes
        $request = $request->withAttribute('validated_data', $data);

        return $handler->handle($request);
    }
}
