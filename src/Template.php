<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Exteon\FileHelper;

    class Template
    {
        /** @var string */
        private $path;

        /** @var string */
        private $name;

        /** @var string */
        private $basePath;

        /** @var string */
        private $dir;

        /**
         * @throws Exception
         */
        public function __construct(string $path, string $basePath)
        {
            $this->path = $path;
            $this->dir = dirname($path);
            $this->name = pathinfo(dirname($path), PATHINFO_BASENAME);
            $this->basePath = $basePath;
        }

        /**
         * @throws Exception
         */
        public function getCompiled(
            TemplateCompileContext $context,
            bool $absolutePath = false
        ): string {
            $context->setTemplateUsed($this);
            $content = $this->getContent();
            $TEMPLATE_DIR =
                $absolutePath ?
                    realpath($this->dir) :
                    FileHelper::getRelativePath(
                        $this->dir,
                        $context->getContextPath(),
                        true
                    );
            $content = $this->replaceTemplateDirVariable(
                $content,
                $TEMPLATE_DIR
            );
            $content = $this->parseTemplates($content, $context, $absolutePath);
            return $content;
        }

        private function getContent(): string
        {
            return file_get_contents($this->path);
        }

        private function replaceTemplateDirVariable(
            string $content,
            string $TEMPLATE_DIR
        ): string {
            return preg_replace(
                '`(?<=\\s)\\$TEMPLATE_DIR(?=\\W)`',
                $TEMPLATE_DIR,
                $content
            );
        }

        /**
         * @param string $content
         * @param TemplateCompileContext $context
         * @param bool $absolutePath
         * @return string
         * @throws Exception
         */
        private function parseTemplates(
            string $content,
            TemplateCompileContext $context,
            bool $absolutePath
        ): string {
            return preg_replace_callback(
                '`^#TEMPLATE\\[\\s*(\\S*?)\\s*]\\s*$`m',
                function ($match) use ($context, $absolutePath): string {
                    $templateName = trim($match[1]);
                    $template = $this->pickTemplate($templateName, $context);
                    if (!$template) {
                        throw new Exception("Unknown template $templateName");
                    }
                    if ($context->isTemplateUsed($template)) {
                        return '';
                    }
                    return
                        "# Compiled from template $templateName ({$template->getPath()})\n" .
                        $template->getCompiled($context, $absolutePath);
                },
                $content
            );
        }

        /**
         * @param string $templateName
         * @param TemplateCompileContext $context
         * @return Template|null
         * @throws Exception
         */
        private function pickTemplate(
            string $templateName,
            TemplateCompileContext $context
        ): ?Template {
            $externalTemplates = [];
            $ownTemplate = null;
            foreach ($context->getTemplates() as $template) {
                if ($template->getName() === $templateName) {
                    if ($template->getBasePath() === $this->basePath) {
                        if ($ownTemplate) {
                            throw new Exception(
                                'Ambiguous own template "' . $templateName . '"'
                            );
                        }
                        $ownTemplate = $template;
                    }
                    $externalTemplates[] = $template;
                }
            }
            if ($externalTemplates) {
                if (count($externalTemplates) > 1) {
                    throw new Exception(
                        'Ambiguous external template "' . $templateName . '"'
                    );
                }
                return reset($externalTemplates);
            }
            return null;
        }

        /**
         * @return string
         */
        public function getName(): string
        {
            return $this->name;
        }

        public function getBasePath(): string
        {
            return $this->basePath;
        }

        /**
         * @return string
         */
        public function getPath(): string
        {
            return $this->path;
        }

    }