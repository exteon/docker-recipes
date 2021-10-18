<?php

    use Exteon\DockerRecipes\DockerComposeCompiler;

    require_once(__DIR__ . '/../vendor/autoload.php');

    (new DockerComposeCompiler(
        [
            new \Exteon\DockerRecipes\StdDockerComposeLocator(
                __DIR__ . '/sources/default'
            ),
            new \Exteon\DockerRecipes\StdDockerComposeLocator(
                __DIR__ . '/sources/deploy'
            )
        ],
        __DIR__ . '/target',
        __DIR__ . '/docker-compose.yml',
        __DIR__
    ))->compile();