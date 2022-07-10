<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Exteon\FileHelper;
    use Exteon\FileHelper\Exception\NotAPrefixException;
    use Symfony\Component\Yaml\Yaml;

    class DockerComposeCompiler
    {
        private DockerfileCompiler $dockerfileCompiler;

        /** @var DockerComposeLocator[] */
        private array $locators;

        private string $projectRoot;
        private string $composeFileTargetPath;

        /** @var string[] */
        private array $appEnv;

        private string $composeFileTargetContext;
        private string $dockerfilesTargetDir;

        /**
         * @param DockerComposeLocator[] $locators
         * @param string $dockerfilesTargetDir
         * @param string $composeFileTargetPath
         * @param string $projectRoot
         * @param string[] $appEnv
         */
        public function __construct(
            array $locators,
            string $dockerfilesTargetDir,
            string $composeFileTargetPath,
            string $projectRoot,
            array $appEnv = ['']
        ) {
            $this->locators = $locators;
            $this->dockerfileCompiler = new DockerfileCompiler(
                array_map(
                    function (DockerComposeLocator $locator
                    ): DockerfileLocator {
                        return $locator->getDockerfileLocator();
                    },
                    $locators
                ),
                $dockerfilesTargetDir,
                $appEnv
            );
            $this->projectRoot = $projectRoot;
            $this->composeFileTargetPath = $composeFileTargetPath;
            $this->composeFileTargetContext = dirname($composeFileTargetPath);
            $this->appEnv = $appEnv;
            $this->dockerfilesTargetDir = $dockerfilesTargetDir;
        }

        /**
         * @throws Exception
         */
        public function compile(): void
        {
            $this->dockerfileCompiler->compile();
            if ($this->locators) {
                $dockerComposeFiles = array_merge(
                    ...array_map(
                       /**
                        * @return DockerComposeFile[]
                        */
                           fn(DockerComposeLocator $locator): array => $locator->getDockerComposeFiles(),
                           $this->locators
                       )
                );
                $merged = array_reduce(
                    array_map(
                        [$this,'compileDockerComposeFile'],
                        $dockerComposeFiles
                    ),
                    [DockerComposeFile::class, 'mergeConfigs']
                );
                if (!FileHelper::preparePath($this->composeFileTargetContext)) {
                    throw new Exception("Cannot create target dir");
                }
                file_put_contents(
                    $this->composeFileTargetPath,
                    Yaml::dump($merged, PHP_INT_MAX, 2)
                );
            }
        }

        /**
         * @throws Exception
         */
        private function findDockerfile(
            ?string $dockerfilePath,
            ?string $image,
        ): ?Dockerfile {
            $dockerfiles = $this->dockerfileCompiler->getDockerfiles();
            if ($dockerfilePath !== null) {
                foreach ($dockerfiles as $dockerfile) {
                    if ($dockerfile->getPath() === $dockerfilePath) {
                        return $dockerfile;
                    }
                }
                return null;
            }
            if ($image !== null) {
                if (preg_match('`^(.*?)/(.*)$`', $image, $match)) {
                    $appEnv = $match[1];
                    $imageName = $match[2];
                } else {
                    $appEnv = null;
                    $imageName = $image;
                }
                if ($appEnv !== null) {
                    foreach ($dockerfiles as $dockerfile) {
                        if (
                            $dockerfile->getName() === $imageName &&
                            $dockerfile->getAppEnv() === $appEnv
                        ) {
                            return $dockerfile;
                        }
                    }
                } else {
                    foreach ($this->appEnv as $comAppEnv) {
                        foreach ($dockerfiles as $dockerfile) {
                            if (
                                $dockerfile->getName() === $imageName &&
                                $dockerfile->getAppEnv() === $comAppEnv
                            ) {
                                return $dockerfile;
                            }
                        }
                    }
                    foreach ($dockerfiles as $dockerfile) {
                        if (
                            $dockerfile->getName() === $imageName &&
                            $dockerfile->getAppEnv() === ''
                        ) {
                            return $dockerfile;
                        }
                    }
                }
            }
            return null;
        }

        /**
         * @throws Exception
         */
        public function mapPath(string $relPath, DockerComposeFile $dockerComposeFile): string
        {
            if (
                preg_match(
                    '`^\s*\$\{PROJECT_DIR(?:-.*?)?}(.*)`',
                    $relPath,
                    $match
                )
            ) {
                $absPath = FileHelper::applyRelativePath(
                    $this->projectRoot,
                    $match[1],
                    true
                );
            } else {
                $absPath = FileHelper::applyRelativePath(
                    $dockerComposeFile->getContext(),
                    $relPath,
                    true
                );
            }
            try {
                return
                    './' .
                    FileHelper::getRelativePath($absPath, $this->composeFileTargetContext);
            } catch (NotAPrefixException) {
                return $absPath;
            }
        }

        /**
         * @throws NotAPrefixException
         * @throws Exception
         */
        function compileDockerComposeFile(DockerComposeFile $dockerComposeFile): array
        {
            $cfg = $dockerComposeFile->getContent();
            if (!$cfg['services'] ?? []) {
                return $cfg;
            }

            foreach ($cfg['services'] as &$service) {
                $contextEff = $service['build'] ?? null;
                if (is_array($contextEff)) {
                    $contextEff = $contextEff['context'] ?? null;
                }
                $context =
                    $contextEff ??
                    $dockerComposeFile->getContext();
                $dockerfile =
                    $service['build']['dockerfile'] ??
                    'Dockerfile';
                if (!FileHelper::isAbsolutePath($dockerfile)) {
                    $dockerfile = FileHelper::applyRelativePath(
                        $context,
                        $dockerfile
                    );
                }
                if (!FileHelper::isAbsolutePath($dockerfile)) {
                    $dockerfile = FileHelper::applyRelativePath(
                        $dockerComposeFile->getContext(),
                        $dockerfile
                    );
                }
                if (
                    $contextEff !== null ||
                    ($service['build']['dockerfile'] ?? null) !== null
                ) {
                    $explicitDockerfile = $dockerfile;
                } else {
                    $explicitDockerfile = null;
                }

                $image = $service['image'] ?? null;
                if (
                    $image !== null &&
                    preg_match(
                        '`^(.*):[^/]*$`',
                        $image,
                        $match
                    )
                ) {
                    $image = $match[1];
                }
                $dockerfile = $this->findDockerfile($explicitDockerfile, $image);
                if ($dockerfile) {
                    if (!is_array($service['build'] ?? null)) {
                        $service['build'] = [];
                    }
                    $service['build']['context'] = FileHelper::getRelativePath(
                        $this->dockerfilesTargetDir,
                        $this->composeFileTargetContext,
                        true
                    );
                    $service['build']['dockerfile'] = FileHelper::getRelativePath(
                        $dockerfile->getTargetPath(),
                        $this->dockerfilesTargetDir,
                        true
                    );
                }
                if (is_array($service['volumes'] ?? null)) {
                    foreach ($service['volumes'] as &$volume) {
                        if (is_string($volume)) {
                            $volumeParts = explode(':', $volume);
                            $volume = [];
                            $last = array_pop($volumeParts);
                            if (preg_match($last[0], '[a-zA-Z0-9]')) {
                                $volume['mode'] = $last;
                                $volume['target'] = array_pop($volumeParts);
                            } else {
                                $volume['target'] = $last;
                            }
                            if ($volumeParts) {
                                $volume['source'] = array_pop($volumeParts);
                                if (
                                    preg_match(
                                        $volume['source'][0],
                                        '[a-zA-Z0-9]'
                                    )
                                ) {
                                    $volume['type'] = 'volume';
                                } else {
                                    $volume['type'] = 'bind';
                                }
                            } else {
                                $volume['type'] = 'bind';
                            }
                        }
                        if (
                            $volume['type'] === 'bind' &&
                            $volume['source'][0] !== '/' &&
                            $volume['source'][0] !== '~'
                        ) {
                            $volume['source'] = $this->mapPath($volume['source'], $dockerComposeFile);
                        }
                    }
                    unset($volume);
                }
            }
            unset($service);

            return $cfg;
        }


    }