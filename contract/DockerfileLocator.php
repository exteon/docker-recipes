<?php

    namespace Exteon\DockerRecipes;

    use Exception;

    interface DockerfileLocator
    {
        /**
         * @return Template[]
         * @throws Exception
         */
        public function getTemplates(): array;

        /**
         * @return Dockerfile[]
         * @throws Exception
         */
        public function getDockerfiles(): array;
    }