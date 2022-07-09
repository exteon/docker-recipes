<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Exteon\FileHelper;
    use RuntimeException;

    class Template
    {
        private string $path;
        private string $name;
        private string $dir;
        private string $appEnv;

        /**
         * @throws Exception
         */
        public function __construct(string $path, string $name, string $appEnv)
        {
            $this->path = $path;
            $this->dir = dirname($path);
            $this->name = $name;
            $this->appEnv = $appEnv;
        }

        /**
         * @throws Exception
         */
        public function compile(
            TemplateCompileContext $context,
            string $targetDir
        ): string {
            $context->setTemplateUsed($this);
            $TEMPLATE_DIR = ($this->appEnv ? $this->appEnv . '/' : '') . $this->name;
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
                    if (!FileHelper::preparePath($contextPath)) {
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
                                throw new RuntimeException('Could not copy context file');
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
            return $this->parseTemplates($content, $context, $targetDir);
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
                    $fullName = trim($match[1]);
                    if (preg_match('`^(.*?)/(.*)$`', $fullName, $match)) {
                        $templateName = $match[2];
                        $templateAppEnv = $match[1];
                    } else {
                        $templateName = $fullName;
                        $templateAppEnv = null;
                    }
                    $template = $this->pickTemplate($templateName, $templateAppEnv, $context);
                    if (!$template) {
                        throw new Exception("Unknown template $templateName");
                    }
                    if ($context->isTemplateUsed($template)) {
                        return '';
                    }
                    return
                        "# Compiled from template $templateName" .
                        ($template->getAppEnv() ? '.' . $template->getAppEnv() : '') .
                        " ({$template->getPath()})\n" .
                        $template->compile($context, $targetDir);
                },
                $content
            );
        }

        /**
         * @throws Exception
         */
        private function pickTemplate(
            string $templateName,
            ?string $templateAppEnv,
            TemplateCompileContext $context
        ): ?Template {
            if ($templateAppEnv !== null) {
                foreach ($context->getTemplates() as $template) {
                    if (
                        $template->getName() === $templateName &&
                        $template->getAppEnv() === $templateAppEnv
                    ) {
                        return $template;
                    }
                }
            } else {
                foreach ($context->getAppEnv() as $appEnv) {
                    foreach ($context->getTemplates() as $template) {
                        if (
                            $template->getName() === $templateName &&
                            $template->getAppEnv() === $appEnv
                        ) {
                            return $template;
                        }
                    }
                }
                foreach ($context->getTemplates() as $template) {
                    if (
                        $template->getName() === $templateName &&
                        $template->getAppEnv() === ''
                    ) {
                        return $template;
                    }
                }
            }
            return null;
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function getPath(): string
        {
            return $this->path;
        }

        public function getAppEnv(): string
        {
            return $this->appEnv;
        }

    }