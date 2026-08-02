<?php

declare(strict_types=1);

namespace PHPDocsMD;

use PHPDocsMD\Entities\CodeEntity;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class that can extract information from a function/class comment
 *
 * @package PHPDocsMD
 */
class DocInfoExtractor
{
    /**
     * Tag names addressing an array-valued key in the extracted structure. A
     * free-form tag must never be allowed to take one of these names. "see" is
     * not listed: its own branch above already consumes it.
     */
    private const STRUCTURED_TAGS = ['params'];

    /**
     * @param \ReflectionMethod|\ReflectionClass $reflection
     *
     * @return DocInfo
     */
    public function extractInfo(ReflectionMethod|ReflectionClass $reflection): DocInfo
    {
        $comment = $this->getCleanDocComment($reflection);
        $data = $this->extractInfoFromComment($comment, $reflection);

        return new DocInfo($data);
    }

    private function getCleanDocComment(ReflectionClass|ReflectionMethod $reflection): string
    {
        // getDocComment() returns false for anything undocumented, which is most
        // of what this tool is asked to describe.
        $comment = str_replace(['/*', '*/'], '', (string)$reflection->getDocComment());

        // The "*" decoration only ever sits at the start of a line, so the match
        // is anchored there. Unanchored, it also ate an asterisk in the prose
        // together with the space on each side, turning "width * height" into
        // "widthheight".
        //
        // Both captures are re-emitted. "$1" keeps the indentation that
        // MDTableGenerator::formatExampleComment() measures; "$2" matters on a
        // line holding nothing but " *", where the only whitespace left for
        // "(\s)" to match is the newline itself — dropping it spliced that line
        // onto the next one and silently deleted every blank line inside an
        // @example block. The single leading space this leaves on each line is
        // uniform, so dedent() takes it back off.
        return trim(trim((string)preg_replace('/^([ \t]*)[ \t]\*(\s)/m', '$1$2', $comment)), '*');
    }

    /**
     * @return array|string[]
     */
    private function extractInfoFromComment(
        string $comment,
        ReflectionMethod|ReflectionClass $reflection,
        string $current_tag = 'description'
    ): array {
        $currentNamespace = $this->getNameSpace($reflection);
        $tags = [$current_tag => ''];
        $lastParam = null;

        foreach (explode(PHP_EOL, $comment) as $line) {
            if ($current_tag !== 'example') {
                $line = trim($line);
            }

            $words = $this->getWordsFromLine($line);
            if (empty($words)) {
                // Inside an example a blank line is part of the sample; anywhere
                // else it is only docblock spacing and carries nothing. Skipping
                // it unconditionally is the other half of why the paragraph
                // breaks in an @example disappeared — getCleanDocComment() has
                // to preserve the line, and this has to keep it once preserved.
                if ($current_tag === 'example') {
                    $tags['example'] .= PHP_EOL;
                }

                continue;
            }

            // A tag is the whole first word, either bare or *completely* wrapped
            // in the inline braces phpDocumentor uses ("{@inheritDoc}"). Testing
            // for a contained "@" read an e-mail address at the start of a line
            // as a tag; accepting a bare "{@" opener read the first word of an
            // inline "{@link ...}" as one and swallowed the rest of the line.
            $isTag = preg_match(
                '/^(?:\{@([A-Za-z][\w-]*)\}|@([A-Za-z][\w-]*))$/',
                $words[0],
                $tagMatch
            ) === 1;
            $tagName = $isTag ? ($tagMatch[1] !== '' ? $tagMatch[1] : $tagMatch[2]) : '';

            if (!$isTag) {
                // A wrapped "@param" line continues that parameter's description.
                // "@see" and "@return" hold a structured value, and their wrapped
                // lines are prose this tool does not render. All three used to
                // fall through below and be appended to the entity's description.
                if ($current_tag === 'param') {
                    if ($lastParam !== null) {
                        $tags['params'][$lastParam]['description'] = trim(
                            $tags['params'][$lastParam]['description'] . ' ' . $line
                        );
                    }
                    continue;
                }
                if ($current_tag === 'see' || $current_tag === 'return') {
                    continue;
                }

                // Append to tag
                $joinWith = $current_tag === 'example' ? PHP_EOL : ' ';
                $tags[$current_tag] .= $joinWith . $line;
            } elseif ($tagName === 'param') {
                // Get parameter declaration
                $lastParam = null;
                if ($paramData = $this->figureOutParamDeclaration($words, $currentNamespace)) {
                    [$name, $data] = $paramData;
                    $tags['params'][$name] = $data;
                    $lastParam = $name;
                }
                $current_tag = 'param';
            } elseif ($tagName === 'see') {
                if (!isset($tags['see']) || !is_array($tags['see'])) {
                    $tags['see'] = [];
                }
                $tags['see'][] = $this->figureOutSeeDeclaration($words);
                $current_tag = 'see';
            } elseif ($tagName === 'return') {
                // "@return <type> <description>" is the documented form: only the
                // first word is the type. Storing the whole line rendered the
                // description inside <em> as though it were part of the type.
                $tags['return'] = $words[1] ?? '';
                $current_tag = 'return';
            } elseif (in_array($tagName, self::STRUCTURED_TAGS, true)) {
                // "@params" would replace the array the real "@param" writes into
                // with a string, and the next "@param" would then index that
                // string and fatal. Keep the line as prose instead — never in a
                // tag whose value is not a string.
                $sink = in_array($current_tag, ['param', 'see', 'return'], true)
                    ? 'description'
                    : $current_tag;
                $tags[$sink] .= ' ' . $line;
            } else {
                // Start new tag
                $current_tag = $tagName;
                array_splice($words, 0, 1);
                if (empty($tags[$current_tag])) {
                    $tags[$current_tag] = '';
                }

                $tags[$current_tag] .= trim(implode(' ', $words));
            }
        }

        foreach ($tags as $name => $val) {
            if (is_array($val)) {
                foreach ($val as $subName => $subVal) {
                    if (is_string($subVal)) {
                        /** @psalm-suppress InvalidArrayOffset */
                        $tags[$name][$subName] = trim($subVal);
                    }
                }
            } else {
                $tags[$name] = trim($val);
            }
        }

        return $tags;
    }

    private function getNameSpace(ReflectionMethod|ReflectionClass $reflection): string
    {
        return $reflection instanceof ReflectionClass
            ? $reflection->getNamespaceName()
            : $reflection->getDeclaringClass()->getNamespaceName();
    }

    private function getWordsFromLine(string $line): array
    {
        $words = [];
        foreach (explode(' ', trim($line)) as $w) {
            if (!empty($w)) {
                $words[] = $w;
            }
        }

        return $words;
    }

    private function figureOutParamDeclaration(array $words, string $currentNameSpace): ?array
    {
        // Drop the leading @param tag, everything below indexes the declaration itself
        array_shift($words);

        $description = '';
        $type = '';
        $name = '';

        if (isset($words[0]) && str_starts_with($words[0], '$')) {
            $name = $words[0];
            $type = 'mixed';
            array_splice($words, 0, 1);
        } elseif (isset($words[1])) {
            [$type, $name] = $words;
            array_splice($words, 0, 2);
        }

        if (!empty($name)) {
            $name = current(explode('=', $name));
            $description = implode(' ', $words);

            $type = Utils::sanitizeDeclaration($type, $currentNameSpace);

            $data = [
                'description' => $description,
                'name' => $name,
                'type' => $type,
                'default' => false,
            ];

            // Key by the bare name. Reflector looks a parameter up as "$name",
            // while a docblock may write "...$name" or "&$name" for the same
            // one — those entries used to be unreachable, so the documented type
            // and description of every variadic and by-reference parameter were
            // silently dropped.
            return ['$' . ltrim($name, '&.$'), $data];
        }

        return null;
    }

    private function figureOutSeeDeclaration(array $words): ?string
    {
        array_shift($words);

        if (!$words) {
            $see = null;
        } elseif (preg_match('#^http://|^https://#', $words[0])) {
            $see = count($words) > 1
                ? '[' . implode(' ', array_slice($words, 1)) . '](' . $words[0] . ')'
                : '<' . $words[0] . '>';
        } else {
            $see = implode(' ', $words);
        }

        return $see;
    }

    public function applyInfoToEntity(
        ReflectionMethod|ReflectionClass $reflection,
        DocInfo $docInfo,
        CodeEntity $code
    ): void {
        $code->setName($reflection->getName());
        $code->setDescription($docInfo->getDescription());
        $code->setExample($docInfo->getExample());
        $code->setSee($docInfo->getSee());
        $code->isInternal($docInfo->isInternal());

        // Presence of the tag, not the message: "@deprecated" on its own is the
        // most common form, and testing the message read it as not deprecated.
        if ($docInfo->isDeprecated()) {
            $code->isDeprecated(true);
            $code->setDeprecationMessage($docInfo->getDeprecationMessage());
        }
    }
}
