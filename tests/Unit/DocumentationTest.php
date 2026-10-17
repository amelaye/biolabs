<?php
/**
 * Enforces the documentation of every function of biolabs
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every function and method declared in src/, tests/ and migrations/ must have a docblock with
 * a description, a described @param for each parameter and a @return (except constructors), as in
 * biophp and biotools.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class DocumentationTest extends TestCase
{
    /**
     * Tests that no function or method lacks a complete docblock.
     *
     * @return void
     */
    public function testEveryFunctionIsDocumented(): void
    {
        $problems = [];
        foreach ($this->classesOfTheProject() as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getMethods() as $method) {
                $file = $method->getFileName();
                if (false === $file || !$this->isProjectFile($file)) {
                    continue; // inherited from a library or built in (enum cases(), from()...)
                }
                $problem = $this->problemWith($method);
                if (null !== $problem) {
                    $problems[] = $method->getDeclaringClass()->getName().'::'.$method->getName().' — '.$problem;
                }
            }
        }

        self::assertSame([], array_values(array_unique($problems)), "Functions with an incomplete docblock:\n".implode("\n", array_unique($problems)));
    }

    /**
     * Describes what is missing in the docblock of a method.
     *
     * @param \ReflectionMethod $method Method to inspect
     * @return string|null What is missing, null when the docblock is complete
     */
    private function problemWith(\ReflectionMethod $method): ?string
    {
        $doc = $method->getDocComment();
        if (false === $doc) {
            return 'no docblock';
        }

        $lines = array_map(static fn (string $line): string => trim($line, " \t*/"), explode("\n", $doc));
        $description = array_filter($lines, static fn (string $line): bool => '' !== $line && !str_starts_with($line, '@'));
        if ([] === $description) {
            return 'no description';
        }
        foreach ($method->getParameters() as $parameter) {
            if (1 !== preg_match('/@param\s+.+?\s+\$'.preg_quote($parameter->getName(), '/').'\s+\S/', $doc)) {
                return 'parameter $'.$parameter->getName().' is not described';
            }
        }
        if (!$method->isConstructor() && !str_contains($doc, '@return')) {
            return 'no @return';
        }

        return null;
    }

    /**
     * Tells whether a file belongs to the code written in this project.
     *
     * @param string $file Absolute path
     * @return bool True for src/, tests/ and migrations/
     */
    private function isProjectFile(string $file): bool
    {
        $root = \dirname(__DIR__, 2);

        return str_starts_with($file, $root.'/src/') || str_starts_with($file, $root.'/tests/') || str_starts_with($file, $root.'/migrations/');
    }

    /**
     * Classes, interfaces, traits and enums declared in the project files.
     *
     * @return list<class-string> Fully qualified names
     */
    private function classesOfTheProject(): array
    {
        $root = \dirname(__DIR__, 2);
        $classes = [];
        foreach (['src', 'tests', 'migrations'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ('php' !== $file->getExtension()) {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                if (1 !== preg_match('/^namespace\s+([^;]+);/m', $source, $namespace)
                    || 1 !== preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $source, $name)) {
                    continue;
                }
                $class = $namespace[1].'\\'.$name[1];
                if (class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class)) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }
}
