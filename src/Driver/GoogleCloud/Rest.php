<?php

namespace Csm\Driver\GoogleCloud;

use Google\Cloud\Core\RestTrait;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;
use Google\Cloud\Core\Upload\AbstractUploader;
use Google\Cloud\Core\Upload\MultipartUploader;

/**
 * @author Oleksandr Ieremeev
 * @package Csm
 */
class Rest extends \Google\Cloud\Storage\Connection\Rest
{
    use RestTrait;

    /**
     * @param array $args
     */
    public function downloadObject(array $args = [])
    {
        $args += [
            'bucket' => null,
            'object' => null,
            'generation' => null
        ];

        $requestOptions = array_intersect_key($args, [
            'httpOptions' => null,
            'retries' => null
        ]);

        $uri = $this->expandUri(self::DOWNLOAD_URI, [
            'bucket' => $args['bucket'],
            'object' => $args['object'],
            'query' => [
                'generation' => $args['generation'],
                'alt' => 'media'
            ]
        ]);

        return $this->requestWrapper->send(
            new Request('GET', Psr7\uri_for($uri)),
            $requestOptions
        )->getBody();
    }

    /**
     * @param array $args
     */
    public function insertObject(array $args = [])
    {
        $writeEvent = isset($args['uploaderOptions']['writeEvent']) ?
            $args['uploaderOptions']['writeEvent'] : null;
        $chainElement = isset($args['uploaderOptions']['chainElement']) ?
            $args['uploaderOptions']['chainElement'] : null;

        $args = $this->resolveUploadOptions($args);
        $isResumable = $args['resumable'];
        $uploadType = $isResumable
            ? AbstractUploader::UPLOAD_TYPE_RESUMABLE
            : AbstractUploader::UPLOAD_TYPE_MULTIPART;

        $uriParams = [
            'bucket' => $args['bucket'],
            'query' => [
                'predefinedAcl' => $args['predefinedAcl'],
                'uploadType' => $uploadType
            ]
        ];

        if ($isResumable) {
            return new ResumableUploader(
                $this->requestWrapper,
                $args['data'],
                $this->expandUri(self::UPLOAD_URI, $uriParams),
                $args['uploaderOptions'] + ['writeEvent' => $writeEvent, 'chainElement' => $chainElement]
            );
        }

        return new MultipartUploader(
            $this->requestWrapper,
            $args['data'],
            $this->expandUri(self::UPLOAD_URI, $uriParams),
            $args['uploaderOptions']
        );
    }

    /**
     * @param array $args
     */
    private function resolveUploadOptions(array $args)
    {
        $args += [
            'bucket' => null,
            'name' => null,
            'validate' => true,
            'resumable' => null,
            'predefinedAcl' => 'private',
            'metadata' => []
        ];

        $args['data'] = Psr7\stream_for($args['data']);

        if ($args['resumable'] === null) {
            $args['resumable'] = $args['data']->getSize() > AbstractUploader::RESUMABLE_LIMIT;
        }

        if (!$args['name']) {
            $args['name'] = basename($args['data']->getMetadata('uri'));
        }

        // @todo add support for rolling hash
        if ($args['validate'] && !isset($args['metadata']['md5Hash'])) {
            $args['metadata']['md5Hash'] = base64_encode(Psr7\hash($args['data'], 'md5', true));
        }

        $args['metadata']['name'] = $args['name'];
        unset($args['name']);
        $args['contentType'] = isset($args['metadata']['contentType'])
            ? $args['metadata']['contentType']
            : Psr7\mimetype_from_filename($args['metadata']['name']);

        $uploaderOptionKeys = [
            'httpOptions',
            'retries',
            'chunkSize',
            'contentType',
            'metadata',
        ];

        $args['uploaderOptions'] = array_intersect_key($args, array_flip($uploaderOptionKeys));
        $args = array_diff_key($args, array_flip($uploaderOptionKeys));

        return $args;
    }
}
