<?php

namespace RoyBongers\CertbotDns01\Certbot;

class ChallengeRecord
{
    /**
     * @var string
     */
    private $domain;
    /**
     * @var string
     */
    private $challengeName;
    /**
     * @var string
     */
    private $challengeValue;

    public function __construct(string $domain, string $challengeName, string $challengeValue)
    {
        $this->domain = $domain;
        $this->challengeName = $challengeName;
        $this->challengeValue = $challengeValue;
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @return string
     */
    public function getChallengeName(): string
    {
        return $this->challengeName;
    }

    /**
     * @return string
     */
    public function getChallengeValue(): string
    {
        return $this->challengeValue;
    }
}
