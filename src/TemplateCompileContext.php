<?php

    namespace Exteon\DockerRecipes;

    use SplObjectStorage;

    class TemplateCompileContext
    {
        /** @var Template[] */
        private array $templates;

        /** @var SplObjectStorage<Template,bool> */
        private SplObjectStorage $isTemplateUsed;

        /** @var string[] */
        private array $appEnv;

        /**
         * @param Template[] $templates
         * @param string[] $appEnv
         */
        public function __construct(array $templates, array $appEnv = null)
        {
            $this->templates = $templates;
            $this->isTemplateUsed = new SplObjectStorage();
            $this->appEnv = $appEnv;
        }

        /**
         * @return Template[]
         */
        public function getTemplates(): array
        {
            return $this->templates;
        }

        public function setTemplateUsed(Template $template): void
        {
            $this->isTemplateUsed[$template] = true;
        }

        public function isTemplateUsed(Template $template): bool
        {
            return $this->isTemplateUsed[$template] ?? false;
        }

        /**
         * @return string[]
         */
        public function getAppEnv(): array
        {
            return $this->appEnv;
        }
    }