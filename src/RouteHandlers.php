<?php

namespace Phaster\RouteHandlers;

use Phaster\Entities;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Teapot\{HttpException, StatusCode};

function search_route($class)
{
    return function (ServerRequestInterface $request, ResponseInterface $response) use ($class): ResponseInterface {
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

        /** @var Entities $instance */
        $instance = new $class();
        $entities = $instance->getEntities($params->q, $params->sort, $params->offset, $params->limit);
        $response->getBody()->write(json_encode(['data' => $entities]));
        return $response;
    };
}

function get_one_route($class)
{
    return function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($class): ResponseInterface {
        /** @var Entities $instance */
        $instance = new $class();
        $response->getBody()->write(json_encode(['data' => $instance->getEntityById($args['id'])]));
        return $response;
    };
}

function insert_route($class)
{
    return function (ServerRequestInterface $request, ResponseInterface $response) use ($class): ResponseInterface {
        /** @var Entities $instance */
        $instance = new $class();
        $data = $request->getParsedBody();

        if (isset($data[0])) {
            // bulk insert
            $body = ['ids' => $instance->addEntities($data)];
        } else {
            $body = ['id' => $instance->addEntities([$data])[0]];
        }

        $response->getBody()->write(json_encode($body));
        return $response;
    };
}

function update_route($class)
{
    return function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($class): ResponseInterface {
        /** @var Entities $instance */
        $instance = new $class();
        $data = $request->getParsedBody();
        $affected = $instance->updateById($args['id'], $data);

        if ($affected === 0) {
            throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
        }

        return $response->withStatus(StatusCode::NO_CONTENT);
    };
}

function patch_route($class)
{
    return function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($class): ResponseInterface {
        /** @var Entities $instance */
        $instance = new $class();
        $data = $request->getParsedBody();
        $affected = $instance->patchByIds(explode(',', $args['id']), $data);

        if ($affected === 0) {
            throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
        }

        return $response->withStatus(StatusCode::NO_CONTENT);
    };
}

function delete_route($class)
{
    return function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($class): ResponseInterface {
        /** @var Entities $instance */
        $instance = new $class();
        $affected = $instance->deleteByIds(explode(',', $args['id']));

        if ($affected === 0) {
            throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
        }

        return $response->withStatus(StatusCode::NO_CONTENT);
    };
}
