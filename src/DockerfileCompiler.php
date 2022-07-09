<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Exteon\FileHelper;
    use RuntimeException;

    class DockerfileCompiler
    {
        private string $targetDir;

        /** @var DockerfileLocator[] */
        private array $locators;

        /** @var Dockerfile[] */
        private ?array $dockerfiles = null;

        /** @var string[]  */
        private array $appEnv;

        /**
         * @param DockerfileLocator[] $locators
         * @param string $targetDir
         * @param string[] $appEnv
         */
        public function __construct(
            array $locators,
            string $targetDir,
            array $appEnv = ['']
        ) {
            $this->targetDir = $targetDir;
            $this->locators = $locators;
            $this->appEnv = $appEnv;
        }

        /**
         * @throws Exception
         */
        public function compile(): void
        {
            if ($this->locators) {
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
                if (
                    file_exists($this->targetDir) &&
                    !FileHelper::rmDir($this->targetDir, false)
                ) {
                    throw new RuntimeException('Could not clean target dir');
                }
                foreach ($this->getDockerfiles() as $dockerfile) {
                    $dockerfile->compile(
                        new TemplateCompileContext($templates, $this->appEnv),
                        $this->targetDir
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