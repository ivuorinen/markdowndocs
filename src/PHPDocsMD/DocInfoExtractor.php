<?php

namespace PHPDocsMD;

use PHPDocsMD\Entities\CodeEntity;

/**
 * Class that can extract information from a function/class comment
 *
 * @package PHPDocsMD
 */
class DocInfoExtractor
{
    /**
     * @param \ReflectionClass|\ReflectionMethod $reflection
     *
     * @return DocInfo
     */
    public function extractInfo($reflection): DocInfo
    {
        $comment = $this->getCleanDocComment($reflection);
        $data    = $this->extractInfoFromComment($comment, $reflection);

        return new DocInfo($data);
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod $reflection
     *
     * @return string
     */
    private function getCleanDocComment(\Reflector $reflection): string
    {
        $comment = str_replace([ '/*', '*/' ], '', $reflection->getDocComment());

        return trim(trim(preg_replace('/([\s|^]\*\s)/', '', $comment)), '*');
    }

    private function extractInfoFromComment(string $comment, $reflection, string $current_tag = 'description'): array
    {
        $currentNamespace = $this->getNameSpace($reflection);
        $tags             = [ $current_tag => '' ];

        foreach (explode(PHP_EOL, $comment) as $line) {
            if ($current_tag !== 'example') {
                $line = trim($line);
            }

            $words = $this->getWordsFromLine($line);
            if (empty($words)) {
                continue;
            }

            if (strpos($words[0], '@') === false) {
                // Append to tag
                $joinWith             = $current_tag === 'example' ? PHP_EOL : ' ';
                $tags[ $current_tag ] .= $joinWith . $line;
            } elseif ($words[0] === '@param') {
                // Get parameter declaration
                if ($paramData = $this->figureOutParamDeclaration($words, $currentNamespace)) {
                    [ $name, $data ] = $paramData;
                    $tags['params'][ $name ] = $data;
                }
            } elseif ($words[0] === '@see') {
                $tags['see'][] = $this->figureOutSeeDeclaration($words);
            } else {
                // Start new tag
                $current_tag = substr($words[0], 1);
                array_splice($words, 0, 1);
                if (empty($tags[ $current_tag ])) {
                    $tags[ $current_tag ] = '';
                }
                $tags[ $current_tag ] .= trim(implode(' ', $words));
            }
        }

        foreach ($tags as $name => $val) {
            if (is_array($val)) {
                foreach ($val as $subName => $subVal) {
                    if (is_string($subVal)) {
                        $tags[ $name ][ $subName ] = trim($subVal);
                    }
                }
            } else {
                $tags[ $name ] = trim($val);
            }
        }

        return $tags;
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod $reflection
     *
     * @return string
     */
    private function getNameSpace($reflection): string
    {
        return $reflection instanceof \ReflectionClass
            ? $reflection->getNamespaceName()
            : $reflection->getDeclaringClass()->getNamespaceName();
    }

    private function getWordsFromLine($line): array
    {
        $words = [];
        foreach (explode(' ', trim($line)) as $w) {
            if (! empty($w)) {
                $words[] = $w;
            }
        }

        return $words;
    }

    private function figureOutParamDeclaration(array $words, string $currentNameSpace)
    {
        $description = '';
        $type        = '';
        $name        = '';

        if (isset($words[1]) && strpos($words[1], '$') === 0) {
            $name = $words[1];
            $type = 'mixed';
            array_splice($words, 0, 2);
        } elseif (isset($words[2])) {
            $name = $words[2];
            $type = $words[1];
            array_splice($words, 0, 3);
        }

        if (! empty($name)) {
            $name = current(explode('=', $name));
            if (count($words) > 1) {
                $description = implode(' ', $words);
            }

            $type = Utils::sanitizeDeclaration($type, $currentNameSpace);

            $data = [
                'description' => $description,
                'name'        => $name,
                'type'        => $type,
                'default'     => false,
            ];

            return [ $name, $data ];
        }

        return false;
    }

    private function figureOutSeeDeclaration(array $words)
    {
        array_shift($words);

        if (! $words) {
            $see = false;
        } elseif (preg_match('#^http://|^https://#', $words[0])) {
            $see = count($words) > 1
                ? '[' . implode(' ', array_slice($words, 1)) . '](' . $words[0] . ')'
                : '<' . $words[0] . '>';
        } else {
            $see = implode(' ', $words);
        }

        return $see;
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod $reflection
     * @param DocInfo                            $docInfo
     * @param CodeEntity                         $code
     */
    public function applyInfoToEntity($reflection, DocInfo $docInfo, CodeEntity $code): void
    {
        $code->setName($reflection->getName());
        $code->setDescription($docInfo->getDescription());
        $code->setExample($docInfo->getExample());
        $code->setSee($docInfo->getSee());
        $code->isInternal($docInfo->isInternal());

        if ($docInfo->getDeprecationMessage()) {
            $code->isDeprecated(true);
            $code->setDeprecationMessage($docInfo->getDeprecationMessage());
        }
    }
}
