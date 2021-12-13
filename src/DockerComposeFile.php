<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Exteon\FileHelper;
    use Exteon\FileHelper\Exception\NotAPrefixException;
    use Symfony\Component\Yaml\Yaml;

    class DockerComposeFile
    {
        /** @var string */
        private $dockerComposeFilePath;

        /** @var Dockerfile[] */
        private $dockerfiles;

        /** @var string */
        private $context;
        /**
         * @var string
         */
        private $targetDir;

        /**
         * @param string $dockerComposeFilePath
         * @param Dockerfile[] $dockerfiles
         * @param string $targetDir
         */
        public function __construct(
            string $dockerComposeFilePath,
            array $dockerfiles,
            string $targetDir
        ) {
            $this->dockerComposeFilePath = $dockerComposeFilePath;
            $this->context = dirname($dockerComposeFilePath);
            $this->dockerfiles = $dockerfiles;
            $this->targetDir = $targetDir;
        }

        /**
         * @throws Exception
         */
        public static function mergeConfigs(?array $into, array $what): array
        {
            if ($into === null) {
                $into = ['version' => '3'];
            }
            if (
                !is_string($into['version'] ?? null) ||
                !is_string($what['version'] ?? null) ||
                !preg_match('`^3\\.?`', $into['version']) ||
                !preg_match('`^3\\.?`', $what['version'])
            ) {
                throw new Exception(
                    'Version must be specified and ^3.0 in docker compose files'
                );
            }
            $v1 = (int)(explode('.', $into['version'])[1] ?? 0);
            $v2 = (int)(explode('.', $what['version'])[1] ?? 0);
            $v =
                $v1 < $v2 ?
                    $v2 :
                    $v1;
            $result = static::mergeConfigsPartial($into, $what);
            $result['version'] = $v ? "3.$v" : "3";
            return $result;
        }

        /**
         * @throws Exception
         */
        public static function mergeConfigsPartial(
            array $into,
            array $what
        ): array {
            $result = $into;
            foreach ($what as $key => $value) {
                if("$key" === (string)(int)$key){
                    $result[] = $value;
                } else {
                    if (
                        is_array($value) &&
                        is_array($into[$key] ?? null)
                    ) {
                        $result[$key] = static::mergeConfigsPartial(
                            $into[$key],
                            $value
                        );
                    } else {
                        $result[$key] = $value;
                    }
                }
            }
            return $result;
        }

        /**
         * @param string $contextPath
         * @param bool $absolutePath
         * @return array
         * @throws Exception
         */
        public function getCompiled(
            string $contextPath,
            bool $absolutePath = false
        ): array {
            $cfg = Yaml::parseFile($this->dockerComposeFilePath);
            if (!$cfg['services'] ?? []) {
                return $cfg;
            }

            foreach ($cfg['services'] as &$service) {
                $contextEff = $service['build'] ?? null;
                if (is_array($contextEff)) {
                    $contextEff = $contextEff['context'] ?? null;
                }
                $context =
                    $contextEff ??
                    $this->context;
                $dockerfile =
                    $service['build']['dockerfile'] ??
                    'Dockerfile';
                if (!FileHelper::isAbsolutePath($dockerfile)) {
                    $dockerfile = FileHelper::applyRelativePath(
                        $context,
                        $dockerfile
                    );
                }
                if (!FileHelper::isAbsolutePath($dockerfile)) {
                    $dockerfile = FileHelper::applyRelativePath(
                        $this->context,
                        $dockerfile
                    );
                }
                if (
                    $contextEff !== null ||
                    ($service['build']['dockerfile'] ?? null) !== null
                ) {
                    $explicitDockerfile = $dockerfile;
                } else {
                    $explicitDockerfile = null;
                }

                $image = $service['image'] ?? null;
                if (
                    $image !== null &&
                    preg_match(
                        '`^(.*):[^/]*$`',
                        $image,
                        $match
                    )
                ) {
                    $image = $match[1];
                }
                $dockerfile = $this->findDockerfile(
                    $explicitDockerfile,
                    $image
                );
                if ($dockerfile) {
                    if(!is_array($service['build'])){
                        $service['build'] = [];
                    }
                    $service['build']['context'] =
                        $absolutePath ?
                            $contextPath :
                            FileHelper::getRelativePath(
                                $contextPath,
                                $this->targetDir,
                                true
                            );
                    $service['build']['dockerfile'] =
                        $absolutePath ?
                            $dockerfile->getTargetPath() :
                            FileHelper::getRelativePath(
                                $dockerfile->getTargetPath(),
                                $contextPath,
                                true
                            );
                }
                if (is_array($service['volumes'] ?? null)) {
                    foreach ($service['volumes'] as &$volume) {
                        if (is_string($volume)) {
                            $volumeParts = explode(':', $volume);
                            $volume = [];
                            $last = array_pop($volumeParts);
                            if (preg_match($last[0], '[a-zA-Z0-9]')) {
                                $volume['mode'] = $last;
                                $volume['target'] = array_pop($volumeParts);
                            } else {
                                $volume['target'] = $last;
                            }
                            if ($volumeParts) {
                                $volume['source'] = array_pop($volumeParts);
                                if (
                                    preg_match(
                                        $volume['source'][0],
                                        '[a-zA-Z0-9]'
                                    )
                                ) {
                                    $volume['type'] = 'volume';
                                } else {
                                    $volume['type'] = 'bind';
                                }
                            } else {
                                $volume['type'] = 'bind';
                            }
                        }
                        if (
                            $volume['type'] === 'bind' &&
                            $volume['source'][0] !== '/' &&
                            $volume['source'][0] !== '~'
                        ) {
                            $volume['source'] = $this->mapPath(
                                $volume['source']
                            );
                        }
                    }
                    unset($volume);
                }
            }
            unset($service);

            return $cfg;
        }

        /**
         * @throws Exception
         */
        private function findDockerfile(
            ?string $dockerfilePath,
            ?string $image
        ): ?Dockerfile {
            if ($dockerfilePath !== null) {
                foreach ($this->dockerfiles as $dockerfile) {
                    if ($dockerfile->getPath() === $dockerfilePath) {
                        return $dockerfile;
                    }
                }
                return null;
            }
            if ($image !== null) {
                $found = [];
                foreach ($this->dockerfiles as $dockerfile) {
                    if ($dockerfile->getName() === $image) {
                        if ($dockerfile->getDir() === $this->context) {
                            return $dockerfile;
                        }
                        $found[] = $dockerfile;
                    }
                }
                if ($found) {
                    if (count($found) > 1) {
                        throw new Exception(
                            "Ambiguous dockerfile resolution '$image'"
                        );
                    }
                    return reset($found);
                }
            }
            return null;
        }

        /**
         * @throws Exception
         */
        private function mapPath(string $relPath): string
        {
            if (
                preg_match(
                    '`^\s*\$\{PROJECT_DIR(?:-.*?)?}(.*)`',
                    $relPath,
                    $match
                )
            ) {
                return './' . $match[1];
            }
            $absPath = FileHelper::applyRelativePath(
                $this->context,
                $relPath,
                true
            );
            try {
                return
                    './' .
                    FileHelper::getRelativePath($absPath, $this->targetDir);
            } catch (NotAPrefixException $e) {
                return $absPath;
            }
        }
    }