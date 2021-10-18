<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Exteon\FileHelper;
    use Symfony\Component\Yaml\Yaml;

    class DockerComposeCompiler
    {
        /** @var DockerfileCompiler */
        private $dockerfileCompiler;

        /** @var DockerComposeLocator[] */
        private $locators;

        /** @var string */
        private $sourceRoot;

        /** @var string */
        private $composeFileTargetPath;

        /** @var bool */
        private $absolutePath;

        /**
         * @param DockerComposeLocator[] $locators
         * @param string $dockerfilesTargetDir
         * @param string $composeFileTargetPath
         * @param string $sourceRoot
         * @param bool $absolutePath
         */
        public function __construct(
            array $locators,
            string $dockerfilesTargetDir,
            string $composeFileTargetPath,
            string $sourceRoot,
            bool $absolutePath = false
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
                $sourceRoot
            );
            $this->sourceRoot = $sourceRoot;
            $this->composeFileTargetPath = $composeFileTargetPath;
            $this->absolutePath = $absolutePath;
        }

        /**
         * @throws Exception
         */
        public function compile(): void
        {
            $this->dockerfileCompiler->compile($this->absolutePath);
            $composeFileDir = FileHelper::getAscendPath($this->composeFileTargetPath);
            if($this->locators){
                $dockerComposeFiles = array_merge(
                    ...array_map(
                       /**
                        * @param DockerComposeLocator $locator
                        * @return DockerComposeFile[]
                        * @throws Exception
                        */
                           function (DockerComposeLocator $locator) use ($composeFileDir): array {
                               $dockerComposeFilePath = $locator->getDockerComposeFile(
                               );
                               if ($dockerComposeFilePath !== null) {
                                   return [
                                       new DockerComposeFile(
                                           $dockerComposeFilePath,
                                           $this->dockerfileCompiler->getDockerfiles(
                                           ),
                                           $composeFileDir
                                       )
                                   ];
                               }
                               return [];
                           },
                           $this->locators
                       )
                );
                $merged = array_reduce(
                    array_map(
                        function (DockerComposeFile $dockerComposeFile): array {
                            return $dockerComposeFile->getCompiled(
                                $this->sourceRoot,
                                $this->absolutePath
                            );
                        },
                        $dockerComposeFiles
                    ),
                    [DockerComposeFile::class, 'mergeConfigs']
                );
                if (!FileHelper::preparePath($composeFileDir)) {
                    throw new Exception("Cannot create target dir");
                }
                file_put_contents(
                    $this->composeFileTargetPath,
                    Yaml::dump($merged, PHP_INT_MAX, 2)
                );
            }
        }
    }