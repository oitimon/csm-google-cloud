<?php

namespace Csm\Driver\GoogleCloud;

use Csm\Containers\ChainElement;
use Csm\CsmException;
use Google\Cloud\Exception\GoogleException;
use Google\Cloud\Storage\Bucket;

/**
 * @author Oleksandr Ieremeev
 * @package Csm
 */
trait System
{
    use \Csm\Driver\Filesystem\System;

    /**
     * @var Bucket
     */
    protected $bucket;

    /**
     * If "noFileCache" set as true clear filesystem cache.
     *
     * @return void
     */
    protected function clearFileCache()
    {
        // do nothing
    }

    /**
     * @throws CsmException
     * @return Bucket
     */
    protected function getBucket()
    {
        if ($this->bucket === null) {
            try {
                putenv('GOOGLE_APPLICATION_CREDENTIALS='.$this->getParam(static::PARAM_CREDENTIALS_PATH));
                $this->bucket = (new \Csm\Driver\GoogleCloud\StorageClient([
                    'projectId' => $this->getParam(static::PARAM_PROJECT_ID)
                ]))->bucket($this->getParam(static::PARAM_BUCKET));
            } catch (GoogleException $e) {
                throw new CsmException($e);
            }
        }
        return $this->bucket;
    }

    /**
     * Save content directly to os's filesystem.
     *
     * @param string $path
     * @param string $name
     * @param string $content
     * @param int $mode
     * @return bool
     */
    protected function saveFile($path, $name, $content, $mode = 777)
    {
        $result = false;
        try {
            $chainElement = ChainElement::start(strlen($content));
            if ($this->callWriteEvent($chainElement)) {
                $options = [
                    'name'          => $path.'-'.$name,
                    'predefinedAcl' => $mode
                ];
                if ($this->getParam(static::PARAM_WRITE_EVENT)) {
                    $options['uploaderOptions'] = [
                        'writeEvent'   => $this->getParam(static::PARAM_WRITE_EVENT),
                        'chainElement' => $chainElement
                    ];
                }
                if ($this->getParam(static::PARAM_CHAIN_SIZE) > 0) {
                    $options['chunkSize'] = $this->getChunkSize();
                }
                $this->getBucket()->getResumableUploader($content, $options)->upload();
                $result = true;
            } else {
                try {
                    $result = $this->deleteFile($path, $name)/* && $result*/;
                } catch (\Exception $e) {
                    // nothing
                }
            }
        } catch (\Exception $e) {
            try {
                $result = $this->deleteFile($path, $name) && $result;
            } catch (\Exception $e) {
                // nothing
            }
            @$this->generateSystemError($e->getMessage());
        }

        return $result;
    }

    /**
     * @param mixed $chunkSize
     * @return int
     */
    protected function getChunkSize()
    {
        $chunkSize = $this->getParam(static::PARAM_CHAIN_SIZE);
        if ($chunkSize > 0) {
            $chunkSize = ((int)($chunkSize / 262144) + 1) * 262144;
        }
        return $chunkSize > 0 ? $chunkSize : -1;
    }

    /**
     * @param string $errorMsg
     * @return void
     */
    protected function generateSystemError($errorMsg)
    {
        trigger_error($errorMsg, E_USER_WARNING);
    }

    /**
     * Read content from filesystem.
     * Returns false if can not read file.
     *
     * @param string $path
     * @param string $name
     * @return string | boolen
     */
    protected function readFile($path, $name)
    {
        try {
            $object = $this->getBucket()->object($path.'-'.$name);
            $size = $object->info()['size'];
            $chainElement = ChainElement::start($size);
            if ($this->callReadEvent($chainElement)) {
                $stream = $this->getBucket()->object($path . '-' . $name)->downloadAsStream();
                if ($this->getParam(static::PARAM_CHAIN_SIZE) > 0) {
                    $result = '';
                    while (!$stream->eof()) {
                        $result .= $stream->read($this->getParam(static::PARAM_CHAIN_SIZE));
                        if (!$this->callReadEvent($chainElement->update(strlen($result)))) {
                            $result = false;
                            break;
                        }
                    }
                } else {
                    $result = $stream->getContents();
                    if (!$this->callReadEvent($chainElement->update(strlen($result)))) {
                        $result = false;
                    }
                }
                $stream->close();
            } else {
                $result = false;
            }
        } catch (\Exception $e) {
            @$this->generateSystemError($e->getMessage());
            $result = false;
        }
        return $result;
    }

    /**
     * Copy file by name from source path to destination.
     * Returns false if error.
     *
     * @param string $sourcePath
     * @param string $destPath
     * @param string $name
     * @param string $destName
     * @return bool
     */
    protected function copyFile($sourcePath, $destPath, $name, $destName = '')
    {
        if ($destName == '') {
            $destName = $name;
        }
        try {
            $object = $this->getBucket()->object($sourcePath.'-'.$name);
            $object->copy(
                $this->getBucket(),
                ['name' => $destPath.'-'.$destName]
            );
            $result = true;
        } catch (\Exception $e) {
            @$this->generateSystemError($e->getMessage());
            $result = false;
        }
        return $result;
    }

    /**
     * Check content in the filesystem.
     * Returns false if file is not present.
     *
     * @param string $path
     * @param string $name
     * @return bool
     */
    protected function isFilePresent($path, $name)
    {
        try {
            $result = $this->getBucket()->object($path.'-'.$name)->exists();
        } catch (\Exception $e) {
            @$this->generateSystemError($e->getMessage());
            $result = false;
        }
        return $result;
    }

    /**
     * Delete content from filesystem.
     * Returns false if error.
     *
     * @param string $path
     * @param string $name
     * @return bool
     */
    protected function deleteFile($path, $name)
    {
        try {
            $this->getBucket()->object($path.'-'.$name)->delete();
            $result = true;
        } catch (\Exception $e) {
            @$this->generateSystemError($e->getMessage());
            $result = false;
        }
        return $result;
    }

    /**
     * @param resource $fp
     * @return string | bool
     */
    protected function tryReadResource($fp, ChainElement $chainElement)
    {
        $content = false;
        $chainSize = $this->getParam(static::PARAM_CHAIN_SIZE);
        while (!feof($fp)) {
            $content = (string)$content.@fread($fp, $chainSize);
            if (!$this->callReadEvent($chainElement->update(strlen($content)))) {
                $content = false;
                break;
            }
        }
        return @fclose($fp) ? $content : false;
    }

    /**
     * @param resource $fp
     * @param string $content
     * @return bool
     */
    protected function tryWriteResource($fp, $content, ChainElement $chainElement)
    {
        $chainSize = $this->getParam(static::PARAM_CHAIN_SIZE);
        $pieces = str_split($content, $chainSize);
        $saved = 0;
        $result = true;
        foreach ($pieces as $piece) {
            $saved += @fwrite($fp, $piece, strlen($piece));
            $result = $this->callWriteEvent($chainElement->update($saved));
            if (!$result) {
                break;
            }
        }
        return @fclose($fp) && $result;
    }
}
