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
    private $webDir;
    private $router;

    /**
     * Constructor
     *
     * @param mixed $container app container
     */
    public function __construct(
        Filesystem $filesystem,
        RouterInterface $router,
        $path,
        $webDir,
        $allowedTypes
    ) {
        $this->filesystem = $filesystem;
        $this->path = $path;
        $this->allowedTypes = $allowedTypes;
        $this->router = $router;
        $this->webDir = $webDir;
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
        $fileMimeType = $file->getClientMimeType();
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
            preg_replace(
                '/\s+/',
                '-',
                $this->translit($originalName)
            )
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
        $adapter = $this->filesystem->getAdapter();
        if ($adapter instanceof AwsS3) {
            return $adapter->getUrl($name);
        } elseif ($adapter instanceof Local) {
            $context = $this->router->getContext();

            return sprintf(
                '%s://%s/%s/%s',
                $context->getScheme(),
                $context->getHost(),
                $this->getWebPath(),
                $name
            );
        }
    }

    public function listFiles()
    {
        $adapter = $this->filesystem->getAdapter();
        if ($adapter instanceof AwsS3) {
            return $this->filesystem->listKeys();
        } elseif ($adapter instanceof Local) {
            $files = $this->filesystem->listKeys();
            $pathnames = [];
            foreach($files['keys'] as $file){
                $pathnames[] = $this->getWebpath().'/'.$file;
            }

            return $pathnames;
        }
    }

    /**
     * Encode windows to utf-8
     *
     * @param object $s string for encoding
     *
     * @return string
     */
    private static function win2utf($s)
    {
        return iconv('cp1251', 'utf-8', $s);
    }

    /**
     * Encode utf-8 to windows
     *
     * @param string $s string for encoding
     *
     * @return string
     */
    private static function utf2win($s)
    {
        return iconv('utf-8', 'cp1251', $s);
    }

    /**
     * Return ru-en translited string
     *
     * @param string $var message string for transliteration
     *
     * @return string
     */
    public function translit($var)
    {
        $var = strtolower(trim(self::utf2win($var)));

        $cyr = array_map(
            function ($elem) {
                self::utf2win($elem);
            },
            [
                'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м',
                'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ',
                'ы', 'ь', 'э', 'ю', 'я', 'А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З',
                'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х',
                'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я'
            ]
        );

        $lat = [
            'a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z', 'i', 'y', 'k', 'l', 'm',
            'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', '',
            'y', '', 'e', 'yu', 'ya', 'a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z',
            'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h',
            'c', 'ch', 'sh', 'sz', '', 'y', '', 'e', 'yu', 'ya'
        ];

        $var = str_replace($cyr, $lat, $var);
        $var = str_replace('-', ' ', $var);
        $var = str_replace('/', ' ', $var);

        $var = preg_replace('/[^a-z0-9-\.]+/', ' ', $var);
        $var = preg_replace('/(\s+)/', '-', trim($var));
        $var = self::win2utf($var);

        return $var;
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

    public function getWebPath()
    {
        $webRoot = realpath($this->webDir);
        $path = realpath($this->getPath());
        $webPath = substr($path, strpos($path, $webRoot) + strlen($webRoot));

        return trim($webPath, '/');
    }
}
