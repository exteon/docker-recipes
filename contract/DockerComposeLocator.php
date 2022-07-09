<?php

    namespace Exteon\DockerRecipes;

    interface DockerComposeLocator
    {
        /**
         * @return DockerComposeFile[]
         */
        public function getDockerComposeFiles(): array;

        public function getDockerfileLocator(): DockerfileLocator;
    }