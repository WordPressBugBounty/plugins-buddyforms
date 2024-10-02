<?php
 namespace tk\ReCaptcha; class ReCaptcha { const VERSION = 'php_1.2.4'; const SITE_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify'; const E_INVALID_JSON = 'invalid-json'; const E_CONNECTION_FAILED = 'connection-failed'; const E_BAD_RESPONSE = 'bad-response'; const E_UNKNOWN_ERROR = 'unknown-error'; const E_MISSING_INPUT_RESPONSE = 'missing-input-response'; const E_HOSTNAME_MISMATCH = 'hostname-mismatch'; const E_APK_PACKAGE_NAME_MISMATCH = 'apk_package_name-mismatch'; const E_ACTION_MISMATCH = 'action-mismatch'; const E_SCORE_THRESHOLD_NOT_MET = 'score-threshold-not-met'; const E_CHALLENGE_TIMEOUT = 'challenge-timeout'; private $secret; private $requestMethod; public function __construct($secret, \tk\ReCaptcha\RequestMethod $requestMethod = null) { if (empty($secret)) { throw new \RuntimeException('No secret provided'); } if (!\is_string($secret)) { throw new \RuntimeException('The provided secret must be a string'); } $this->secret = $secret; $this->requestMethod = \is_null($requestMethod) ? new \tk\ReCaptcha\RequestMethod\Post() : $requestMethod; } public function verify($response, $remoteIp = null) { if (empty($response)) { $recaptchaResponse = new \tk\ReCaptcha\Response(\false, array(self::E_MISSING_INPUT_RESPONSE)); return $recaptchaResponse; } $params = new \tk\ReCaptcha\RequestParameters($this->secret, $response, $remoteIp, self::VERSION); $rawResponse = $this->requestMethod->submit($params); $initialResponse = \tk\ReCaptcha\Response::fromJson($rawResponse); $validationErrors = array(); if (isset($this->hostname) && \strcasecmp($this->hostname, $initialResponse->getHostname()) !== 0) { $validationErrors[] = self::E_HOSTNAME_MISMATCH; } if (isset($this->apkPackageName) && \strcasecmp($this->apkPackageName, $initialResponse->getApkPackageName()) !== 0) { $validationErrors[] = self::E_APK_PACKAGE_NAME_MISMATCH; } if (isset($this->action) && \strcasecmp($this->action, $initialResponse->getAction()) !== 0) { $validationErrors[] = self::E_ACTION_MISMATCH; } if (isset($this->threshold) && $this->threshold > $initialResponse->getScore()) { $validationErrors[] = self::E_SCORE_THRESHOLD_NOT_MET; } if (isset($this->timeoutSeconds)) { $challengeTs = \strtotime($initialResponse->getChallengeTs()); if ($challengeTs > 0 && \time() - $challengeTs > $this->timeoutSeconds) { $validationErrors[] = self::E_CHALLENGE_TIMEOUT; } } if (empty($validationErrors)) { return $initialResponse; } return new \tk\ReCaptcha\Response(\false, \array_merge($initialResponse->getErrorCodes(), $validationErrors), $initialResponse->getHostname(), $initialResponse->getChallengeTs(), $initialResponse->getApkPackageName(), $initialResponse->getScore(), $initialResponse->getAction()); } public function setExpectedHostname($hostname) { $this->hostname = $hostname; return $this; } public function setExpectedApkPackageName($apkPackageName) { $this->apkPackageName = $apkPackageName; return $this; } public function setExpectedAction($action) { $this->action = $action; return $this; } public function setScoreThreshold($threshold) { $this->threshold = \floatval($threshold); return $this; } public function setChallengeTimeout($timeoutSeconds) { $this->timeoutSeconds = $timeoutSeconds; return $this; } } 