<?php

namespace App\Traits;

use App\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

/**
 * Provides request validation functionality to controllers
 */
trait ValidatesRequests
{
    /**
     * Validate the request data against the given rules
     *
     * @param Request $request The request object
     * @param array $rules Validation rules
     * @param string $dataSource Where to get the data from (query, parsedBody, attributes, etc.)
     * @return array|false The validated data or false if validation fails
     */
    protected function validate(Request $request, array $rules, string $dataSource = 'parsedBody')
    {
        $validator = $this->container->get(\App\Validation\Validator::class);
        
        // Add rules to the validator
        foreach ($rules as $field => $fieldRules) {
            $validator->addRule($field, $fieldRules);
        }
        
        // Get data from the specified source
        $data = [];
        switch ($dataSource) {
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
                $method = 'get' . ucfirst($dataSource);
                if (method_exists($request, $method)) {
                    $data = (array) $request->$method();
                }
        }
        
        // Validate the data
        if (!$validator->validate($data)) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Create a validation error response
     *
     * @param array $errors Validation errors
     * @return Response
     */
    protected function validationErrorResponse(array $errors): Response
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'error' => [
                'code' => 422,
                'message' => 'Validation failed',
                'errors' => $errors
            ]
        ], JSON_PRETTY_PRINT));
        
        return $response
            ->withStatus(422)
            ->withHeader('Content-Type', 'application/json');
    }
}
