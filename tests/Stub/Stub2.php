<?php

namespace Csm\Tests\Stub;

use Csm\CsmException;
use Csm\Driver\GoogleCloud;

class Stub2 extends GoogleCloud
{

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
        try {
            $this->prepareOneElementDirectory('/nono', 777);
        } catch (CsmException $e) {
            // nothing
        }
        return false;
    }
}
