<?php

namespace Phaster\RouteHandlers;

use Phaster\Entities;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Teapot\{HttpException, StatusCode};

function search_route($class)
{
    return function (ServerRequestInterface $request, ResponseInterface $response) use ($class): ResponseInterface {
        $params = [
            'q' => [],
            'sort' => [],
            'page' => null,
            'pageSize' => null,
        ];

        foreach ($request->getQueryParams() as $param => $value) {
            if (array_key_exists($param, $params)) {
                $params[$param] = $value;
            } else {
                throw new HttpException("Unrecognized parameter '{$param}'", StatusCode::BAD_REQUEST);
            }
        }

        if ($params['page'] === null && $params['pageSize'] !== null) {
            $params['page'] = 1;
        } elseif ($params['pageSize'] === null && $params['page'] !== null) {
            $params['pageSize'] = 100;
        } elseif ($params['pageSize'] === null) {
            $params['pageSize'] = 0;
        }

        /** @var Entities $instance */
        $instance = new $class();
        $entities = $instance->getEntities($params['q'], $params['sort'], $params['page'], $params['pageSize']);
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
