<?php

    namespace Exteon\DockerRecipes;

    use Exception;

    class DockerfileCompiler
    {
        /** @var string */
        private $targetDir;

        /** @var DockerfileLocator[] */
        private $locators;

        /** @var Dockerfile[] */
        private $dockerfiles;

        /** @var string */
        private $sourceRoot;

        /**
         * @param DockerfileLocator[] $locators
         * @param string $targetDir
         * @param string $sourceRoot
         */
        public function __construct(
            array $locators,
            string $targetDir,
            string $sourceRoot
        ) {
            $this->targetDir = $targetDir;
            $this->locators = $locators;
            $this->sourceRoot = $sourceRoot;
        }

        /**
         * @throws Exception
         */
        public function compile(bool $absolutePath = false): void
        {
            if($this->locators){
                /** @var Template[] $templates */
                $templates = array_merge(
                    ...
                    array_map(
                    /**
                     * @param DockerfileLocator $locator
                     * @return Template[]
                     * @throws Exception
                     */
                        function (DockerfileLocator $locator): array {
                            return $locator->getTemplates();
                        },
                        $this->locators
                    )
                );
                foreach ($this->getDockerfiles() as $dockerfile) {
                    $dockerfile->compile(
                        new TemplateCompileContext($templates, $this->sourceRoot),
                        $this->targetDir,
                        $absolutePath
                    );
                }
            }
        }

        /**
         * @return Dockerfile[]
         * @throws Exception
         */
        public function getDockerfiles(): array
        {
            if ($this->dockerfiles === null) {
                $this->dockerfiles = array_merge(
                    ...
                    array_map(
                    /**
                     * @param DockerfileLocator $locator
                     * @return Dockerfile[]
                     * @throws Exception
                     */
                        function (DockerfileLocator $locator): array {
                            return $locator->getDockerfiles();
                        },
                        $this->locators
                    )
                );
            }
            return $this->dockerfiles;
        }
    }