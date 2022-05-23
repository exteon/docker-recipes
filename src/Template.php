<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Exteon\FileHelper;
    use RuntimeException;

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
        public function compile(
            TemplateCompileContext $context,
            string $targetDir
        ): string {
            $context->setTemplateUsed($this);
            $TEMPLATE_DIR = $this->getName();
            $contextPath = FileHelper::getDescendPath($targetDir, $TEMPLATE_DIR);

            {
                /**
                 * Copy contexts into target context because Docker doesn't support following symlinks outside of
                 * context.
                 *
                 * This won't be necessary pending https://github.com/docker/compose/issues/9461
                 *
                 * #hack
                 */
                $descendants = array_filter(
                    FileHelper::getDescendants($this->dir),
                    fn(string $path): bool => ($path != $this->path)
                );
                if (
                    $descendants &&
                    !is_dir($contextPath)
                ) {
                    if(!FileHelper::preparePath($contextPath)){
                        throw new RuntimeException('Could not create context dir');
                    }
                    foreach ($descendants as $descendantPath) {
                        $relPath = FileHelper::getRelativePath($descendantPath, $this->dir);
                        $descendantContextPath = FileHelper::getDescendPath($contextPath, $relPath);
                        if (is_dir($descendantPath)) {
                            if (!mkdir($descendantContextPath)) {
                                throw new RuntimeException('Could not create context directory');
                            }
                        } else {
                            if (!copy($descendantPath, $descendantContextPath)) {
                                throw new \RangeException('Could not copy context file');
                            }
                        }
                    }
                }
            }

            $content = $this->getContent();
            $content = $this->replaceTemplateDirVariable(
                $content,
                $TEMPLATE_DIR
            );
            $content = $this->parseTemplates($content, $context, $targetDir);
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
         * @return string
         * @throws Exception
         */
        private function parseTemplates(
            string $content,
            TemplateCompileContext $context,
            string $targetDir
        ): string {
            return preg_replace_callback(
                '`^#TEMPLATE\\[\\s*(\\S*?)\\s*]\\s*$`m',
                function ($match) use ($context, $targetDir): string {
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
                        $template->compile($context, $targetDir);
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