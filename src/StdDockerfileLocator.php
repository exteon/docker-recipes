<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Exteon\FileHelper;

    class StdDockerfileLocator implements DockerfileLocator
    {
        const TEMPLATES_DIR = 'templates';
        /** @var string */
        private $path;

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
                function (string $path): Template {
                    return new Template(
                        $path,
                        $this->path
                    );
                },
                $this->getDockerfilePaths(
                    FileHelper::getDescendPath(
                        $this->path,
                        static::TEMPLATES_DIR
                    ),
                    []
                )
            );
        }

        /**
         * @param string[] $excludeSubdirs
         * @return string[]
         */
        private function getDockerfilePaths(
            string $path,
            array $excludeSubdirs
        ): array {
            $result = [];
            if (is_dir($path)) {
                $dockerfile = $path . '/Dockerfile';
                if (file_exists($dockerfile)) {
                    $result[] = $dockerfile;
                } else {
                    $subdirs = FileHelper::getChildren($path);
                    foreach ($subdirs as $subdir) {
                        if (
                            is_dir($subdir) &&
                            !in_array(
                                pathinfo($subdir, PATHINFO_BASENAME),
                                $excludeSubdirs
                            )
                        ) {
                            $dockerfile = $subdir . '/Dockerfile';
                            if (file_exists($dockerfile)) {
                                $result[] = $dockerfile;
                            }
                        }
                    }
                }
            } elseif (file_exists($path)) {
                $result[] = $path;
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
                function (string $path): Dockerfile {
                    return new Dockerfile(
                        $path,
                        $this->path
                    );
                },
                $this->getDockerfilePaths(
                    $this->path,
                    [static::TEMPLATES_DIR]
                )
            );
        }
    }