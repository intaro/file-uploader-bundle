<?php

namespace Intaro\FileUploaderBundle\Services;

use Gaufrette\Adapter\AwsS3;
use Symfony\Component\Routing\RouterInterface;
use Gaufrette\Adapter\Local;
use Gaufrette\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploader
{
    private $router;
    private Filesystem $filesystem;
    private string $path;
    private ?array $allowedTypes;
    private \Transliterator $translator;

    public function __construct(
        Filesystem $filesystem,
        RouterInterface $router,
        string $path,
        array $allowedTypes
    ) {
        $this->filesystem = $filesystem;
        $this->path = $path;
        $this->allowedTypes = $allowedTypes;
        $this->router = $router;
        $this->translator = \Transliterator::create('Any-Latin;Latin-ASCII;Lower;[\u0080-\u7fff] remove');
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

        if (!($adapter instanceof Local)) {
            $adapter->setMetadata(
                $filename,
                ['contentType' => $fileMimeType]
            );
        }

        $adapter->write($filename, file_get_contents($file->getPathname()));

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

        if (!($adapter instanceof Local)) {
            $adapter->setMetadata(
                $filename,
                ['contentType' => $fileMimeType]
            );
        }

        $adapter->write($filename, file_get_contents($file->getPathname()));

        if ($unlinkAfterUpload) {
            unlink($file->getPathname());
        }

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
            $this->translator->transliterate($originalName)
        );
    }

    public function remove($name): bool
    {
        return $this->filesystem->delete($name);
    }

    public function getUrl($name): string
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

    public function getAllowedTypes(): ?array
    {
        return $this->allowedTypes;
    }
}
