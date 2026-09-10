<?php declare(strict_types=1);

namespace SqlGuard\Engine\Infra;

use SqlGuard\Engine\Domain\Position;
use SqlGuard\Engine\Port\FileSource;

final class LocalFileSource implements FileSource
{
    /** @param list<string> $excludeDirs */
    public function __construct(
        private readonly string $root,
        private readonly array $excludeDirs = ['vendor', 'node_modules', '.git', 'dist'],
    ) {}

    public function root(): string { return $this->root; }

    public function phpFiles(): iterable
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $f): bool {
                    if ($f->isDir()) {
                        return !in_array($f->getFilename(), $this->excludeDirs, true);
                    }
                    return strtolower($f->getExtension()) === 'php';
                },
            ),
        );
        $paths = [];
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            if ($f->isFile()) {
                $paths[] = Position::canonicalPath($f->getPathname(), $this->root);
            }
        }
        sort($paths, SORT_STRING); // determinisme (NFR-1)
        yield from $paths;
    }

    public function read(string $canonicalPath): string
    {
        $full = rtrim($this->root, '/') . '/' . $canonicalPath;
        $content = @file_get_contents($full);
        if ($content === false) {
            throw new \RuntimeException("lecture impossible : $canonicalPath");
        }
        return $content;
    }
}
