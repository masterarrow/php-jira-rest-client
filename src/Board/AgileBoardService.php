<?php

namespace JiraRestApi\Board;

use JiraRestApi\Configuration\ConfigurationInterface;
use Psr\Log\LoggerInterface;

class AgileBoardService extends \JiraRestApi\JiraClient
{
    private $uri = '/board';

    private $agileVersion = '1.0';

    public function __construct(ConfigurationInterface $configuration = null, LoggerInterface $logger = null, $path = './')
    {
        parent::__construct($configuration, $logger, $path);
        $this->setAPIUri('/rest/agile/'.$this->agileVersion);
    }

    public function get($boardIdOrKey, $paramArray = []): ?AgileIssue
    {
        $response = $this->exec($this->uri.'/'.$boardIdOrKey.$this->toHttpQueryParameter($paramArray), null);

        try {
            return $this->json_mapper->map(
                json_decode($response, false, 512, $this->getJsonOptions()),
                new AgileIssue()
            );
        } catch (\JsonException $exception) {
            $this->log->error("Response cannot be decoded from json\nException: {$exception->getMessage()}");

            return null;
        }
    }

    public function getProjects($boardIdOrKey, $paramArray = []): ?AgileIssue
    {
        $response = $this->exec($this->uri.'/'.$boardIdOrKey.'/project'.$this->toHttpQueryParameter($paramArray), null);

        try {
            return $this->json_mapper->map(
                json_decode($response, false, 512, $this->getJsonOptions()),
                new AgileIssue()
            );
        } catch (\JsonException $exception) {
            $this->log->error("Response cannot be decoded from json\nException: {$exception->getMessage()}");

            return null;
        }
    }
}
