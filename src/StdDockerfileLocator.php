<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Exteon\FileHelper;

    class StdDockerfileLocator implements DockerfileLocator
    {
        const TEMPLATES_DIR = 'templates';

        private string $path;

        public function __construct(string $path)
        {
            $this->path = $path;
        }

        /**
         * @return Template[]
         * @throws Exception
         */
        public function getTemplates(): array
        {
            return array_map(
                fn(array $path): Template => new Template(
                    $path['path'],
                    $path['name'],
                    $path['appEnv']
                ),
                $this->getDockerfilePaths(
                    self::TEMPLATES_DIR,
                    []
                )
            );
        }

        /**
         * @param string[] $excludeSubdirs
         * @return array<array{ path: string, name: string, appEnv: string }>
         */
        private function getDockerfilePaths(
            ?string $appEnvSubpath,
            array $excludeSubdirs
        ): array {
            $result = [];
            if (is_dir($this->path)) {
                $appEnvs = FileHelper::getChildren($this->path);
                foreach ($appEnvs as $appEnvPath) {
                    $appEnv = pathinfo($appEnvPath, PATHINFO_BASENAME);
                    if (
                        is_dir($appEnvPath) &&
                        !in_array(
                            $appEnv,
                            $excludeSubdirs
                        )
                    ) {
                        if ($appEnvSubpath) {
                            $appEnvPath = FileHelper::getDescendPath($appEnvPath, $appEnvSubpath);
                        }
                        $dockerfile = FileHelper::getDescendPath($appEnvPath, 'Dockerfile');
                        if (file_exists($dockerfile)) {
                            $result[] = [
                                'path' => $dockerfile,
                                'name' => $appEnv,
                                'appEnv' => ''
                            ];
                        } elseif (is_dir($appEnvPath)) {
                            $namePaths = FileHelper::getChildren($appEnvPath);
                            foreach ($namePaths as $namePath) {
                                $name = pathinfo($namePath, PATHINFO_BASENAME);
                                if(!in_array($name,$excludeSubdirs)){
                                    $dockerfile = FileHelper::getDescendPath($namePath, 'Dockerfile');
                                    if (file_exists($dockerfile)) {
                                        $result[] = [
                                            'path' => $dockerfile,
                                            'name' => $name,
                                            'appEnv' => $appEnv
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            return $result;
        }

        /**
         * @return Dockerfile[]
         * @throws Exception
         */
        public function getDockerfiles(): array
        {
            return array_map(
                fn(array $path): Dockerfile => new Dockerfile(
                    $path['path'],
                    $path['name'],
                    $path['appEnv']
                ),
                $this->getDockerfilePaths(
                    null,
                    [static::TEMPLATES_DIR]
                )
            );
        }
    }