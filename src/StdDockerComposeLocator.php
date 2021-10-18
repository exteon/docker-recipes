<?php

    namespace Exteon\DockerRecipes;

    use Exteon\FileHelper;

    class StdDockerComposeLocator implements DockerComposeLocator
    {
        /** @var string */
        private $path;

        /** @var StdDockerfileLocator */
        private $dockerfileLocator;

        public function __construct(string $path){
            $this->path = $path;
            $this->dockerfileLocator = new StdDockerfileLocator($path);
        }

        public function getDockerComposeFile(): ?string
        {
            $filename = FileHelper::getDescendPath(
                $this->path,
                'docker-compose.yml'
            );
            if (file_exists($filename)) {
                return $filename;
            }
            return null;
        }

        /**
         * @return StdDockerfileLocator
         */
        public function getDockerfileLocator(): DockerfileLocator
        {
            return $this->dockerfileLocator;
        }
    }