<?php

namespace Csm\Driver\GoogleCloud;

use Google\Cloud\Core\Exception\GoogleException;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Request;
use Google\Cloud\Core\RequestWrapper;
use Psr\Http\Message\StreamInterface;

/**
 * @author Oleksandr Ieremeev
 * @package Csm
 */
class ResumableUploader extends \Google\Cloud\Core\Upload\ResumableUploader
{
    /**
     * @var string
     */
    private $resumeUri;
    /**
     * @var callable
     */
    protected $writeEvent;
    /**
     * @var mixed
     */
    protected $chainElement;

    /**
     * @param RequestWrapper $requestWrapper
     * @param string|resource|StreamInterface $data
     * @param string $uri
     * @param array $options [optional] {
     *     Optional configuration.
     *
     *     @type array $metadata Metadata on the resource.
     *     @type int $chunkSize Size of the chunks to send incrementally during
     *           a resumable upload. Must be in multiples of 262144 bytes.
     *     @type array $httpOptions HTTP client specific configuration options.
     *     @type int $retries Number of retries for a failed request.
     *           **Defaults to** `3`.
     *     @type string $contentType Content type of the resource.
     * }
     */
    public function __construct(
        RequestWrapper $requestWrapper,
        $data,
        $uri,
        array $options = []
    ) {
        parent::__construct($requestWrapper, $data, $uri, $options);
        $this->writeEvent = isset($options['writeEvent']) ? $options['writeEvent'] : null;
        $this->chainElement = isset($options['chainElement']) ? $options['chainElement'] : null;
    }

    /**
     * Triggers the upload process.
     *
     * @return array
     * @throws GoogleException
     */
    public function upload()
    {
        $rangeStart = $this->rangeStart;
        $response = null;
        $resumeUri = $this->getResumeUri();
        $size = $this->data->getSize() ?: '*';

        do {
            $data = new LimitStream(
                $this->data,
                $this->chunkSize ?: - 1,
                $rangeStart
            );
            $rangeEnd = $rangeStart + ($data->getSize() - 1);
            $headers = [
                'Content-Length' => $data->getSize(),
                'Content-Type' => $this->contentType,
                'Content-Range' => "bytes $rangeStart-$rangeEnd/$size",
            ];

            $request = new Request(
                'PUT',
                $resumeUri,
                $headers,
                $data
            );

            try {
                $response = $this->requestWrapper->send($request, $this->requestOptions);
                if ($this->writeEvent &&
                    !call_user_func($this->writeEvent, $this->chainElement->update($rangeEnd + 1))) {
                    throw new GoogleException('user break');
                }
            } catch (GoogleException $ex) {
                throw new GoogleException(
                    "Upload failed. Please use this URI to resume your upload: $this->resumeUri",
                    $ex->getCode()
                );
            }

            $rangeStart = $this->getRangeStart($response->getHeaderLine('Range'));
        } while ($response->getStatusCode() === 308);

        return json_decode($response->getBody(), true);
    }

    /**
     * Gets the starting range for the upload.
     *
     * @param string $rangeHeader
     * @return int
     */
    private function getRangeStart($rangeHeader)
    {
        if (!$rangeHeader) {
            return null;
        }

        return (int) explode('-', $rangeHeader)[1] + 1;
    }
}
