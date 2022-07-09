<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Exteon\FileHelper;

    class Dockerfile
    {
        private string $path;
        private string $name;
        private string $targetPath;
        private Template $template;
        private string $appEnv;

        /**
         * @throws Exception
         */
        public function __construct(string $path, string $name, string $appEnv)
        {
            $this->path = $path;
            $this->name = $name;
            $this->template = new Template($path, $name, $appEnv);
            $this->appEnv = $appEnv;
        }

        /**
         * @throws Exception
         */
        public function compile(
            TemplateCompileContext $context,
            string $targetDir
        ): void {
            $content = $this->template->compile($context, $targetDir);
            $content = "# Compiled from dockerfile $this->name ($this->path)\n\n" . $content;
            $targetPath = $targetDir;
            if ($this->appEnv) {
                $targetPath = FileHelper::getDescendPath($targetPath, $this->appEnv);
            }
            $this->targetPath = FileHelper::getDescendPath($targetPath, $this->name, 'Dockerfile');
            if (!FileHelper::preparePath($this->targetPath, true)) {
                throw new Exception('Could not create target dir');
            }
            file_put_contents($this->targetPath, $content);
        }

        public function getTargetPath(): string
        {
            return $this->targetPath;
        }

        /**
         * @return string
         */
        public function getPath(): string
        {
            return $this->path;
        }

        /**
         * @return string
         */
        public function getName(): string
        {
            return $this->name;
        }

        /**
         * @return string
         */
        public function getAppEnv(): string
        {
            return $this->appEnv;
        }
    }