<?php

namespace JiraRestApi\Label;

use JiraRestApi\Configuration\ConfigurationInterface;
use Psr\Log\LoggerInterface;

class LabelService extends \JiraRestApi\JiraClient
{
    private $uri = '/label';

    /**
     * @return \ArrayObject|null
     */
    public function getAll($paramArray = []): ?\ArrayObject
    {
        $json = $this->exec($this->uri.$this->toHttpQueryParameter($paramArray), null);

        try {
            return $this->json_mapper->mapArray(
                json_decode($json, false),
                new \ArrayObject()
            );
        } catch (\JsonException $exception) {
            $this->log->error("Response cannot be decoded from json\nException: {$exception->getMessage()}");

            return null;
        }
    }
}
