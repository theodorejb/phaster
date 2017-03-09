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
            $params = new \stdClass();
            $params->q = [];
            $params->sort = [];
            $params->offset = null;
            $params->limit = null;

            foreach ($request->getQueryParams() as $param => $value) {
                if (property_exists($params, $param)) {
                    $params->$param = $value;
                } else {
                    throw new HttpException("Unrecognized parameter '{$param}'", StatusCode::BAD_REQUEST);
                }
            }

            if ($params->offset === null && $params->limit !== null) {
                $params->offset = 0;
            } elseif ($params->limit === null && $params->offset !== null) {
                $params->limit = 100;
            } elseif ($params->limit === null) {
                $params->limit = 0;
            }

            $instance = $factory->createEntities($class);
            $entities = $instance->getEntities($params->q, $params->sort, $params->offset, $params->limit);
            $response->getBody()->write(json_encode(['data' => $entities]));
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
