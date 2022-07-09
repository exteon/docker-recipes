<?php

    namespace Exteon\DockerRecipes;

    use Exteon\FileHelper;

    class StdDockerComposeLocator implements DockerComposeLocator
    {
        private string $path;
        private StdDockerfileLocator $dockerfileLocator;

        /** @var string[] */
        private array $appEnv;

        public function __construct(string $path, array $appEnv = ['common'])
        {
            $this->path = $path;
            $this->dockerfileLocator = new StdDockerfileLocator($path);
            $this->appEnv = $appEnv;
        }

        public function getDockerComposeFiles(): array
        {
            $result = [];
            foreach (array_reverse($this->appEnv) as $appEnv) {
                $filename = FileHelper::getDescendPath($this->path, $appEnv, 'docker-compose.yml');
                if (file_exists($filename)) {
                    $result[] = new DockerComposeFile($filename);
                }
            }
            return $result;
        }

        /**
         * @return StdDockerfileLocator
         */
        public function getDockerfileLocator(): DockerfileLocator
        {
            return $this->dockerfileLocator;
        }
    }