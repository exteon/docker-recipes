<?php

    namespace Exteon\DockerRecipes;

    use SplObjectStorage;

    class TemplateCompileContext
    {
        /** @var Template[] */
        private $templates;

        /** @var SplObjectStorage<Template,bool> */
        private $isTemplateUsed;

        /** @var string */
        private $contextPath;

        /**
         * @param Template[] $templates
         */
        public function __construct(array $templates, string $contextPath)
        {
            $this->templates = $templates;
            $this->isTemplateUsed = new SplObjectStorage();
            $this->contextPath = $contextPath;
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
         * @return string
         */
        public function getContextPath(): string
        {
            return $this->contextPath;
        }
    }