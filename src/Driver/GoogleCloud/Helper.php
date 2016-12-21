<?php

namespace Csm\Driver\GoogleCloud;

use Csm\CsmIdent;

/**
 * @author Oleksandr Ieremeev
 * @package Csm
 */
trait Helper
{
    use \Csm\Driver\Traits\Helper;

    /**
     * Returns array of all dirs for Ident.
     *
     * @param CsmIdent $ident
     * @return array
     */
    protected function getPathAsArray(CsmIdent $ident)
    {
        $dirs = [];

        foreach ($ident->toArray() as $row) {
            $dirs = array_merge($dirs, $this->prepareElementDir($row['i']));
        }

        return $dirs;
    }

    /**
     * Return full path.
     *
     * @param string $startPath
     * @param array $dirs
     * @param int $mode
     * @return string
     */
    protected function prepareFullPath($startPath, array $dirs, $mode = 777)
    {
        return str_replace('/', '-', $this->getFullPath($startPath, $dirs));
    }
}
