<?php

namespace FrontBundle\Imagine;

use AppBundle\Api\Manager\RebrickableManager;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;

class PartImageLoader extends BaseImageLoader
{
    /** @var Filesystem */
    private $mediaFilesystem;

    /** @var RebrickableManager */
    private $rebrickableManager;

    private $rebrickableContext = 'http://rebrickable.com/media/parts/ldraw/';

    /**
     * PartImageLoader constructor.
     *
     * @param RebrickableManager  $rebrickableManager
     * @param FilesystemInterface $mediaFilesystem
     */
    public function __construct(RebrickableManager $rebrickableManager, FilesystemInterface $mediaFilesystem)
    {
        $this->mediaFilesystem = $mediaFilesystem;
        $this->rebrickableManager = $rebrickableManager;
    }

    public function find($path)
    {
        $localPath = '/images/'.$path.'.png';

        // try to load image from local mediaFilesystem
        if ($this->mediaFilesystem->has($localPath)) {
            return $this->mediaFilesystem->read($localPath);
        }

        $rebrickablePath = $this->rebrickableContext.strtolower($path).'.png';

        // try to load image from rebrickable website
        if ($this->remoteFileExists($rebrickablePath)) {
            $context = stream_context_create(['http' => ['header' => 'Connection: close\r\n']]);

            return file_get_contents($rebrickablePath, false, $context);
        }

        // Load part entity form rebrickable api and get image path from response
        try {
            if (preg_match('/^(.*)\/(.*)$/', $path, $match)) {
                $part = $this->rebrickableManager->getPart($match[2]);

                if ($part && $part->getImgUrl()) {
                    return file_get_contents($part->getImgUrl());
                }
            }
        } catch (\Exception $e) {
            throw new NotLoadableException(sprintf('Source image %s could not be loaded.', $path), $e->getCode(), $e);
        }

        throw new NotLoadableException(sprintf('Source image %s not found.', $path));
    }
}
