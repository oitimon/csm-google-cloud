<?php

namespace Csm\Driver;

use Csm\Contracts\Driver;
use Csm\CsmIdent;
use Csm\Driver\GoogleCloud\Helper;
use Csm\Driver\GoogleCloud\System;
use Csm\Driver\Traits\Main;

/**
 * @author Oleksandr Ieremeev
 * @package Csm
 */
class GoogleCloud implements Driver
{
    use Main, Helper, System;

    /**
     * Get content URL by Ident and Name.
     * If content is absent returns false in strict mode or generated url if is not strict.
     *
     * @param CsmIdent $ident
     * @param string $name
     * @throws CsmException
     * @return string | bool
     */
    public function getPreparedUrl(CsmIdent $ident, $name)
    {
        $dirPaths = $this->getPathAsArray($ident);
        $path = $this->prepareFullPath(
            $this->params[static::PARAM_RESOURCE_PATH],
            $dirPaths,
            $this->params[static::PARAM_DIR_MODE]
        );

        try {
            $info = $this->getBucket()->object($path . '-' . $name)->info();
            $result = isset($info['mediaLink']) ? $info['mediaLink'] : false;
        } catch (\Exception $e) {
            $result = false;
        }
        return $result;
    }

    const PARAM_CREDENTIALS_PATH = 'credentialsPath';
    const PARAM_RESOURCE_PATH    = 'resourcePath';
    const PARAM_RESOURCE_URL     = 'resourceUrl';
    const PARAM_DIR_MODE         = 'dirMode';
    const PARAM_FILE_MODE        = 'fileMode';
    const PARAM_IS_STRICT        = 'strict';
    const PARAM_CHAIN_SIZE       = 'chainSize';
    const PARAM_READ_EVENT       = 'readEvent';
    const PARAM_WRITE_EVENT      = 'writeEvent';
    const PARAM_PROJECT_ID       = 'projectId';
    const PARAM_BUCKET           = 'bucket';

    const PARAMS = [
        'credentialsPath' => null,
        'projectId'       => null,
        'bucket'          => null,
        'resourcePath'    => null,
        'resourceUrl'     => null,
        'dirMode'         => 775,
        'fileMode'        => 'publicread',
        'strict'          => true,
        'chainSize'       => null,
        'readEvent'       => false,
        'writeEvent'      => false
    ];
}
