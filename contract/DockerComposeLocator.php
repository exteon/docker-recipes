<?php

    namespace Exteon\DockerRecipes;

    interface DockerComposeLocator
    {
        public function getDockerComposeFile(): ?string;

        /**
         * @return DockerfileLocator
         */
        public function getDockerfileLocator(): DockerfileLocator;
    }