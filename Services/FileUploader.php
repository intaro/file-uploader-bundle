<?php
namespace Intaro\FileUploaderBundle\Services;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;
use Gaufrette\Filesystem;
use Gaufrette\Adapter\Local;
use Gaufrette\Adapter\AwsS3;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class FileUploader
 *
 * @package Intaro\FileUploaderBundle\Services
 */
class FileUploader
{
    private $filesystem;
    private $path;
    private $allowedTypes;
    private $router;
    private $translator;

    /**
     * Constructor
     *
     * @param mixed $container app container
     */
    public function __construct(
        Filesystem $filesystem,
        RouterInterface $router,
        $path,
        $allowedTypes
    ) {
        $this->filesystem = $filesystem;
        $this->path = $path;
        $this->allowedTypes = $allowedTypes;
        $this->router = $router;
        $this->translator = \Transliterator::create('Any-Latin;Latin-ASCII;Lower;[\u0080-\u7fff] remove');
    }

    /**
     * Upload method
     *
     * @param UploadedFile $file file to upload
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function upload(UploadedFile $file)
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

    /**
     * Generate new filename based on original name
     *
     * @param  string $originalName
     * @return string
     */
    public function generateNameByOriginal($originalName)
    {
        return sprintf(
            '%s-%s',
            uniqid(),
            $this->clearName($originalName)
        );
    }

    /**
     * Name cleanup
     * @param $originalName
     * @return string
     */
    protected function clearName($originalName)
    {
        //basic check on URL encoding
        if (urldecode($originalName) !== $originalName) {
            $originalName = urldecode($originalName);
        }

        $originalName = preg_replace(
            '/[\+\\/\%]+/',
            '_',
            $originalName
        );

        return preg_replace(
            '/\s+/',
            '-',
            $this->translator->transliterate($originalName)
        );
    }

    /**
     * Upload local file to storage
     *
     * @access public
     * @param  mixed  $pathname
     * @param  bool   $unlinkAfterUpload (default: true)
     * @return string
     */
    public function uploadByPath($pathname, $unlinkAfterUpload = true)
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

    public function remove($name)
    {
        return $this->filesystem->delete($name);
    }

    public function getUrl($name)
    {
        return $this->getPath().$name;
    }

    public function listFiles()
    {
        $files = [];
        $keys = $this->filesystem->listKeys();
        $adapter = $this->filesystem->getAdapter();
        if ($adapter instanceof AwsS3) {
            if (sizeof($keys) > 0) {
                foreach ($keys as $file) {
                    $filename = basename($file);
                    if ($filename) {
                        $files[] = $this->getPath().$filename;
                    }
                }
            }
        } elseif ($adapter instanceof Local) {
            if (isset($keys['keys']) && sizeof($keys['keys']) > 0) {
                foreach ($keys['keys'] as $file) {
                    $files[] = $this->getPath().$file;
                }
            }
        }

        return $files;
    }

    public function getFilesystem()
    {
        return $this->filesystem;
    }

    public function setFilesystem($filesystem)
    {
        $this->filesystem = $filesystem;

        return $this;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getAllowedTypes()
    {
        return $this->allowedTypes;
    }
}
