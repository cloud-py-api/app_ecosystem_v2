<?php

declare(strict_types=1);

/**
 *
 * Nextcloud - App Ecosystem V2
 *
 * @copyright Copyright (c) 2023 Andrey Borysenko <andrey18106x@gmail.com>
 *
 * @copyright Copyright (c) 2023 Alexander Piskun <bigcat88@icloud.com>
 *
 * @author 2023 Andrey Borysenko <andrey18106x@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\AppEcosystemV2\Service;

use OCA\AppEcosystemV2\AppInfo\Application;
use Psr\Log\LoggerInterface;

use OCP\IConfig;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IClient;

use OCP\AppFramework\Db\Entity;
use OCA\AppEcosystemV2\Db\ExApp;
use OCA\AppEcosystemV2\Db\ExAppMapper;
use OCA\AppEcosystemV2\Db\ExAppUser;
use OCA\AppEcosystemV2\Db\ExAppUserMapper;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Security\ISecureRandom;

class AppEcosystemV2Service {
	/** @var IConfig */
	private $config;

	/** @var LoggerInterface */
	private $logger;

	/** @var IClient */
	private $client;

	/** @var ExAppMapper */
	private $exAppMapper;

	/** @var IL10N */
	private $l10n;

	/** @var IAppManager */
	private $appManager;

	/** @var ExAppUserMapper */
	private $exAppUserMapper;

	/** @var ISecureRandom */
	private $random;

	public function __construct(
		IConfig $config,
		LoggerInterface $logger,
		IClientService $clientService,
		ExAppMapper $exAppMapper,
		IL10N $l10n,
		IAppManager $appManager,
		ExAppUserMapper $exAppUserMapper,
		ISecureRandom $random,
	) {
		$this->config = $config;
		$this->logger = $logger;
		$this->client = $clientService->newClient();
		$this->exAppMapper = $exAppMapper;
		$this->l10n = $l10n;
		$this->appManager = $appManager;
		$this->exAppUserMapper = $exAppUserMapper;
		$this->random = $random;
	}

	public function getExApp(string $exAppId): ?Entity {
		try {
			return $this->exAppMapper->findByAppId($exAppId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Register exApp
	 * 
	 * @param string $appId
	 * @param array $appData [version, name, config, secret, status, enabled]
	 *
	 * @return ExApp|null
	 */
	public function registerExApp(string $appId, array $appData): ?ExApp {
		try {
			$exApp = $this->exAppMapper->findByAppId($appId);
			if ($exApp !== null) {
				$exApp->setVersion($appData['version']);
				$exApp->setName($appData['name']);
				$exApp->setConfig($appData['config']);
				$secret = $this->random->generate(128); // Temporal random secret
				$exApp->setSecret($secret);
				$exApp->setStatus(json_encode(['active' => true]));
				$exApp->setLastResponseTime(time());
				try {
					$exApp = $this->exAppMapper->update($exApp);
					return $exApp;
				} catch (\Exception $e) {
					$this->logger->error('Error while updating ex app: ' . $e->getMessage());
					return null;
				}
			}
		} catch (DoesNotExistException) {
			$exApp = new ExApp();
			$exApp->setAppId($appId);
			$exApp->setVersion($appData['version']);
			$exApp->setName($appData['name']);
			$exApp->setConfig($appData['config']);
			$secret = $this->random->generate(128); // Temporal random secret
			$exApp->setSecret($secret);
			$exApp->setStatus(json_encode(['active' => true]));
			$exApp->setCreatedTime(time());
			$exApp->setLastResponseTime(time());
			try {
				$exApp = $this->exAppMapper->insert($exApp);
				return $exApp;
			} catch (\Exception $e) {
				$this->logger->error('Error while registering ex app: ' . $e->getMessage());
				return null;
			}
		}
	}

	/**
	 * Unregister ex app
	 *
	 * @param string $appId
	 *
	 * @return Entity|null
	 */
	public function unregisterExApp(string $appId): ?Entity {
		try {
			/** @var ExApp $exApp */
			$exApp = $this->exAppMapper->findByAppId($appId);
			if ($this->exAppMapper->deleteExApp($exApp) !== 1) {
				$this->logger->error('Error while unregistering ex app: ' . $appId);
				return false;
			}
			return $exApp;
		} catch (DoesNotExistException $e) {
			$this->logger->error('Error while unregistering ex app: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Enable ex app
	 * 
	 * @param ExApp $exApp
	 * 
	 * @return array|null
	 */
	public function enableExApp(ExApp $exApp): ?array {
		$exAppEnabled = $this->exAppMapper->findExAppEnabled($exApp->getAppid());
		if ($exAppEnabled['success']) {
			return $exAppEnabled;
		}
		return null;
	}

	/**
	 * Disable ex app
	 * 
	 * @param ExApp $exApp
	 * 
	 * @return bool
	 */
	public function disableExApp(ExApp $exApp): bool {
		if ($this->exAppMapper->updateExAppEnabled($exApp->getAppid(), false) === 1) {
			return true;
		}
		return false;
	}

	/**
	 * Send status check request to ex app (after verify app registration)
	 *
	 * @param string $appId
	 *
	 * @return array
	 */
	public function getAppStatus(string $appId): ?array {
		try {
			$exApp = $this->exAppMapper->findByAppId($appId);
			return json_decode($exApp->getStatus(), true);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function requestToExApp(string $userId, ExApp $exApp, string $route, string $method = 'POST', array $params = []) {
		try {
 			$exAppConfig = json_decode($exApp->getConfig(), true);
			$url = $exAppConfig['protocol'] . '://' . $exAppConfig['host'] . ':' . $exAppConfig['port'] . $route;
			// Check in ex_apps_users
			if (!$this->exAppUserExists($exApp->getAppid(), $userId)) {
				try {
					$this->exAppUserMapper->insert(new ExAppUser([
						'appid' => $exApp->getAppid(),
						'userid' => $userId,
					]));
				} catch (\Exception $e) {
					$this->logger->error('Error while inserting ex app user: ' . $e->getMessage());
					return ['error' => 'Error while inserting ex app user: ' . $e->getMessage()];
				}
			}
			$options = [
				'headers' => [
					'NC-USER-ID' => $userId,
					'NC-VERSION' => $this->config->getSystemValue('version'),
					'AE-VERSION' => $this->appManager->getAppVersion(Application::APP_ID, false),
					'EX-APP-ID' => $exApp->getAppid(),
					'EX-APP-VERSION' => $exApp->getVersion(),
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);

					$url .= '?' . $paramsContent;
				} else {
					$options['json'] = $params;
				}
			}

			$signature = $this->generateRequestSignature($method, $options, $params, $exApp->getSecret());
			$options['headers']['EA-SIGNATURE'] = $signature;

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			return $response;
		} catch (\Exception $e) {
			return ['error' => $e->getMessage()];
		}
	}

	public function generateRequestSignature(string $method, array $options, array $params = [], string $secret): ?string {
		$headers = [];
		if (isset($options['headers']['NC-VERSION'])) {
			$headers['NC-VERSION'] = $options['headers']['NC-VERSION'];
		}
		if (isset($options['headers']['AE-VERSION'])) {
			$headers['AE-VERSION'] = $options['headers']['AE-VERSION'];
		}
		if (isset($options['headers']['EX-APP-ID'])) {
			$headers['EX-APP-ID'] = $options['headers']['EX-APP-ID'];
		}
		if (isset($options['headers']['EX-APP-VERSION'])) {
			$headers['EX-APP-VERSION'] = $options['headers']['EX-APP-VERSION'];
		}
		if (isset($options['headers']['NC-USER-ID']) && $options['headers']['NC-USER-ID'] !== '') {
			$headers['NC-USER-ID'] = $options['headers']['NC-USER-ID'];
		}
		if ($method === 'GET') {
			ksort($params);
			$body = $method . json_encode($params) . json_encode($options['headers']);
		} else {
			$queryParams = array_merge($params, $options['json']);
			ksort($queryParams);
			ksort($options['headers']);
			$body = $method . json_encode($queryParams) . json_encode($options['headers']);
		}
		return hash_hmac('sha256', $body, $secret);
	}

	public function validateExAppRequestToNC(IRequest $request): bool {
		try {
			$exApp = $this->exAppMapper->findByAppId($request->getHeader('EX-APP-ID'));
			$enabled = $exApp->getEnabled();
			if (!$enabled) {
				return false;
			}
			$secret = $exApp->getSecret();
			$exApp->setLastResponseTime(time());
			try {
				$this->exAppMapper->updateLastResponseTime($exApp);
				// TODO: Add check of debug mode for logging each request
			} catch (\Exception $e) {
				$this->logger->error('Error while updating ex app last response time for ex app: ' . $exApp->getAppid() . '. Error: ' . $e->getMessage());
			}
		} catch (DoesNotExistException) {
			return false;
		}
		$method = $request->getMethod();
		$headers = [
			'AE-VERSION' => $request->getHeader('AE-VERSION'),
			'EX-APP-ID' => $request->getHeader('EX-APP-ID'),
			'EX-APP-VERSION' => $request->getHeader('EX-APP-VERSION'),
			'NC-USER-ID' => $request->getHeader('NC-USER-ID'),
		];
		$requestSignature = $request->getHeader('EA-SIGNATURE');
		$queryParams = $request->getParams();
		ksort($queryParams);
		ksort($headers);
		if ($method === 'GET') {
			$body = $method . json_encode($queryParams) . json_encode($headers);
		} else {
			$body = $method . json_encode($queryParams) . json_encode($headers);
		}
		$signature = hash_hmac('sha256', $body, $secret);
		// TODO: Add scope check
		$userId = $request->getHeader('NC-USER-ID');
		if (!$this->exAppUserExists($exApp->getAppid(), $userId)) {
			return false;
		}
		return $signature === $requestSignature;
	}

	private function exAppUserExists(string $appId, string $userId): bool {
		try {
			if ($this->exAppUserMapper->findByAppidUserid($appId, $userId) instanceof ExAppUser) {
				return true;
			}
			return false;
		} catch (DoesNotExistException) {
			return false;
		}
	}
}
