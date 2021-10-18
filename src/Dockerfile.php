<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Exteon\FileHelper;

    class Dockerfile
    {
        /** @var string */
        private $path;

        /** @var string */
        private $dir;

        /** @var string */
        private $name;

        /** @var string */
        private $targetPath;

        /** @var string */
        private $relPath;

        /** @var Template */
        private $template;

        /**
         * @param string $path
         * @param string $basePath
         * @throws Exception
         */
        public function __construct(
            string $path,
            string $basePath
        ) {
            $this->path = $path;
            $this->dir = dirname($path);
            $this->name = pathinfo($this->dir, PATHINFO_BASENAME);
            $this->relPath = FileHelper::getRelativePath($path, $basePath);
            $this->template = new Template($path, $basePath);
        }

        /**
         * @param TemplateCompileContext $context
         * @param string $targetDir
         * @param bool $absolutePath
         * @throws Exception
         */
        public function compile(
            TemplateCompileContext $context,
            string $targetDir,
            bool $absolutePath = false
        ): void {
            $content = $this->template->getCompiled($context, $absolutePath);
            $content =
                "# Compiled from dockerfile $this->name ($this->path)\n\n" .
                $content;
            $this->targetPath = FileHelper::getDescendPath(
                $targetDir,
                $this->relPath
            );
            if (!FileHelper::preparePath($this->targetPath, true)) {
                throw new Exception('Could not create target dir');
            }
            file_put_contents($this->targetPath, $content);
        }

        public function getPath(): string
        {
            return $this->path;
        }

        public function getName(): string
        {
            return $this->name;
        }

        /**
         * @return string
         */
        public function getDir(): string
        {
            return $this->dir;
        }

        /**
         * @return string
         */
        public function getTargetPath(): string
        {
            return $this->targetPath;
        }
    }