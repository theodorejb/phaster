<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Teapot\{HttpException, StatusCode};

class RouteHandler
{
    private $entitiesFactory;

    public function __construct(EntitiesFactory $factory)
    {
        $this->entitiesFactory = $factory;
    }

    public function search($class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response) use ($class, $factory): ResponseInterface {
            $params = [
                'q' => [],
                'sort' => [],
                'offset' => 0,
                'limit' => 0,
            ];

            foreach ($request->getQueryParams() as $param => $value) {
                if (!array_key_exists($param, $params)) {
                    throw new HttpException("Unrecognized parameter '{$param}'", StatusCode::BAD_REQUEST);
                }

                if ($param === 'q' || $param === 'sort') {
                    if (!is_array($value)) {
                        throw new HttpException("Parameter '{$param}' must be an array", StatusCode::BAD_REQUEST);
                    }

                    $params[$param] = $value;
                } elseif (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    throw new HttpException("Parameter '{$param}' must be an integer", StatusCode::BAD_REQUEST);
                } else {
                    $params[$param] = (int)$value;
                }
            }

            if ($params['limit'] === 0 && $params['offset'] !== 0) {
                $params['limit'] = 100;
            } elseif ($params['limit'] > 1000) {
                throw new HttpException('Limit cannot exceed 1000', StatusCode::FORBIDDEN);
            }

            $checkLimit = $params['limit'];

            if ($checkLimit !== 0) {
                $checkLimit++; // request 1 extra item to determine if on the last page
            }

            $instance = $factory->createEntities($class);
            $entities = $instance->getEntities($params['q'], $params['sort'], $params['offset'], $checkLimit);

            if ($checkLimit !== 0) {
                $resultCount = count($entities);
                $lastPage = ($resultCount < $checkLimit);

                if (!$lastPage) {
                    array_pop($entities); // remove extra item
                }

                $output = [
                    'offset' => $params['offset'],
                    'limit' => $params['limit'],
                    'lastPage' => $lastPage,
                    'data' => $entities,
                ];
            } else {
                $output = ['data' => $entities];
            }

            $response->getBody()->write(json_encode($output));
            return $response->withHeader('Content-Type', 'application/json');
        };
    }

    public function getById($class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($class, $factory): ResponseInterface {
            $instance = $factory->createEntities($class);
            $response->getBody()->write(json_encode(['data' => $instance->getEntityById($args['id'])]));
            return $response->withHeader('Content-Type', 'application/json');
        };
    }

    public function insert($class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response) use ($class, $factory): ResponseInterface {
            $instance = $factory->createEntities($class);
            $data = $request->getParsedBody();

            if (isset($data[0])) {
                // bulk insert
                $body = ['ids' => $instance->addEntities($data)];
            } else {
                $body = ['id' => $instance->addEntities([$data])[0]];
            }

            $response->getBody()->write(json_encode($body));
            return $response->withHeader('Content-Type', 'application/json');
        };
    }

    public function update($class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($class, $factory): ResponseInterface {
            $instance = $factory->createEntities($class);
            $affected = $instance->updateById($args['id'], $request->getParsedBody());

            if ($affected === 0) {
                throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
            }

            return $response->withStatus(StatusCode::NO_CONTENT);
        };
    }

    public function patch($class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($class, $factory): ResponseInterface {
            $instance = $factory->createEntities($class);
            $affected = $instance->patchByIds(explode(',', $args['id']), $request->getParsedBody());

            if ($affected === 0) {
                throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
            }

            return $response->withStatus(StatusCode::NO_CONTENT);
        };
    }

    public function delete($class): callable
    {
        $factory = $this->entitiesFactory;

        return function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($class, $factory): ResponseInterface {
            $instance = $factory->createEntities($class);
            $affected = $instance->deleteByIds(explode(',', $args['id']));

            if ($affected === 0) {
                throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
            }

            return $response->withStatus(StatusCode::NO_CONTENT);
        };
    }
}
