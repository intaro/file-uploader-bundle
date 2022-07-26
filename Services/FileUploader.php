<?php

namespace Intaro\FileUploaderBundle\Services;

use Gaufrette\Adapter\AwsS3;
use Gaufrette\Adapter\Local;
use Gaufrette\Adapter\MetadataSupporter;
use Gaufrette\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploader
{
    private Filesystem $filesystem;
    private string $path;
    /** @var array<string> */
    private array $allowedTypes;
    private \Transliterator $translator;

    /** @param array<string> $allowedTypes */
    public function __construct(
        Filesystem $filesystem,
        string $path,
        array $allowedTypes
    ) {
        $this->filesystem = $filesystem;
        $this->path = $path;
        $this->allowedTypes = $allowedTypes;

        $translator = \Transliterator::create('Any-Latin;Latin-ASCII;Lower;[\u0080-\u7fff] remove');
        if (!$translator) {
            throw new \RuntimeException('Failed to create Transliterator');
        }
        $this->translator = $translator;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function upload(UploadedFile $file): string
    {
        $fileMimeType = $file->getMimeType();
        if ($this->allowedTypes && !in_array($fileMimeType, $this->allowedTypes)) {
            throw new \InvalidArgumentException(
                sprintf('Files of type %s are not allowed.', $fileMimeType)
            );
        }

        $filename = $this->generateNameByOriginal($file->getClientOriginalName());

        $adapter = $this->filesystem->getAdapter();

        if ($adapter instanceof MetadataSupporter) {
            $adapter->setMetadata(
                $filename,
                ['contentType' => $fileMimeType]
            );
        }

        $adapter->write($filename, (string) file_get_contents($file->getPathname()));

        return $filename;
    }

    public function uploadByPath(string $pathname, bool $unlinkAfterUpload = true): string
    {
        $file = new File($pathname);
        $filename = $file->getBasename();
        $fileMimeType = $file->getMimeType();

        if ($this->allowedTypes && !in_array($fileMimeType, $this->allowedTypes)) {
            throw new \InvalidArgumentException(
                sprintf('Files of type %s are not allowed.', $fileMimeType)
            );
        }

        $adapter = $this->filesystem->getAdapter();

        if ($adapter instanceof MetadataSupporter) {
            $adapter->setMetadata(
                $filename,
                ['contentType' => $fileMimeType]
            );
        }

        $adapter->write($filename, (string) file_get_contents($file->getPathname()));

        if ($unlinkAfterUpload) {
            unlink($file->getPathname());
        }

        return $filename;
    }

    public function uploadByContent(string $fileContent, string $filename, string $mimeType): string
    {
        if ($this->allowedTypes && !in_array($mimeType, $this->allowedTypes)) {
            throw new \InvalidArgumentException(
                sprintf('Files of type %s are not allowed.', $mimeType)
            );
        }

        $adapter = $this->filesystem->getAdapter();

        if ($adapter instanceof MetadataSupporter) {
            $adapter->setMetadata(
                $filename,
                ['contentType' => $mimeType]
            );
        }

        $adapter->write($filename, $fileContent);

        return $filename;
    }

    public function generateNameByOriginal(string $originalName): string
    {
        return sprintf(
            '%s-%s',
            uniqid(),
            $this->clearName($originalName)
        );
    }

    protected function clearName(string $originalName): string
    {
        //basic check on URL encoding
        if (urldecode($originalName) !== $originalName) {
            $originalName = urldecode($originalName);
        }

        $originalName = preg_replace(
            '/[\+\\/\%\#\?]+/',
            '_',
            $originalName
        );

        return preg_replace(
            '/\s+/',
            '-',
            (string) $this->translator->transliterate($originalName)
        );
    }

    public function remove(string $name): bool
    {
        return $this->filesystem->delete($name);
    }

    public function getUrl(string $name): string
    {
        return $this->getPath() . $name;
    }

    /** @return string[] */
    public function listFiles(): array
    {
        $files = [];
        $keys = $this->filesystem->listKeys();
        $adapter = $this->filesystem->getAdapter();
        if ($adapter instanceof AwsS3) {
            if (count($keys) > 0) {
                foreach ($keys as $file) {
                    $filename = basename($file);
                    if ($filename) {
                        $files[] = $this->getPath() . $filename;
                    }
                }
            }
        } elseif ($adapter instanceof Local) {
            if (isset($keys['keys']) && count($keys['keys']) > 0) {
                foreach ($keys['keys'] as $file) {
                    $files[] = $this->getPath() . $file;
                }
            }
        }

        return $files;
    }

    public function getFilesystem(): Filesystem
    {
        return $this->filesystem;
    }

    public function setFilesystem(Filesystem $filesystem): self
    {
        $this->filesystem = $filesystem;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /** @return array<string> */
    public function getAllowedTypes(): array
    {
        return $this->allowedTypes;
    }
}
