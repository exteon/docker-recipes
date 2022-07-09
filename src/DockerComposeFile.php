<?php

    namespace Exteon\DockerRecipes;

    use Exception;
    use Symfony\Component\Yaml\Yaml;

    class DockerComposeFile
    {
        private string $dockerComposeFilePath;
        private string $context;

        public function __construct(
            string $dockerComposeFilePath
        ) {
            $this->dockerComposeFilePath = $dockerComposeFilePath;
            $this->context = dirname($dockerComposeFilePath);
        }

        public function getContent(): array {
            return Yaml::parseFile($this->dockerComposeFilePath);
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
            $v = max($v1, $v2);
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
                if ("$key" === (string)(int)$key) {
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
         * @return string
         */
        public function getContext(): string
        {
            return $this->context;
        }
    }