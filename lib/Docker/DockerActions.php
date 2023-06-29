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

namespace OCA\AppEcosystemV2\Docker;


use GuzzleHttp\Exception\GuzzleException;
use OCA\AppEcosystemV2\Db\DaemonConfig;
use Psr\Log\LoggerInterface;

class DockerActions {
	public const DOCKER_API_VERSION = 'v1.43';
	private LoggerInterface $logger;
	private \GuzzleHttp\Client $guzzleClient;

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
		$this->guzzleClient = new \GuzzleHttp\Client(
			[
				'curl' => [
					CURLOPT_UNIX_SOCKET_PATH => '/var/run/docker.sock',
				],
			]
		);
	}

	/**
	 * Pull image, create and start container
	 *
	 * @param DaemonConfig $daemonConfig
	 * @param array $imageParams
	 * @param array $containerParams
	 *
	 * @return array
	 */
	public function deployExApp(
		DaemonConfig $daemonConfig,
		array $imageParams,
		array $containerParams,
	): array {
		if ($daemonConfig->getAcceptsDeployId() !== 'docker-install') {
			return ['error' => 'Daemon does not accept docker-install'];
		}
		if (in_array($daemonConfig->getProtocol(), ['unix', 'unix-socket'])) {
			$this->guzzleClient = new \GuzzleHttp\Client(
				[
					'curl' => [
						CURLOPT_UNIX_SOCKET_PATH => $daemonConfig->getHost(), // Set socket path from daemon config
					],
				]
			);
		}
		$pullResult = $this->pullContainer($imageParams['image_name'], $imageParams['image_tag']);
		if (isset($pullResult['error'])) {
			return $pullResult;
		}
		$createResult = $this->createContainer($imageParams['image_name'], $containerParams);
		if (isset($createResult['error'])) {
			return $createResult;
		}
		$startResult = $this->startContainer($createResult['Id']);
		return [$createResult, $startResult];
	}

	public function buildApiUrl(string $url): string {
		return sprintf('http://localhost/%s/%s', self::DOCKER_API_VERSION, $url);
	}

	public function createContainer(string $imageName, array $params = []): array {
		$options['json'] = [
			'Image' => $imageName,
			'Hostname' => $params['hostname'],
		];
		$url = $this->buildApiUrl(sprintf('containers/create?name=%s', urlencode($params['name'])));
		try {
			$response = $this->guzzleClient->post($url, $options);
			return json_decode((string) $response->getBody(), true);
		} catch (GuzzleException $e) {
			$this->logger->error('Failed to create container', ['exception' => $e]);
			error_log($e->getMessage());
			return ['error' => 'Failed to create container'];
		}
	}

	public function startContainer(string $containerId): array {
		$url = $this->buildApiUrl(sprintf('containers/%s/start', $containerId));
		try {
			$response = $this->guzzleClient->post($url);
			return ['success' => $response->getStatusCode() === 204];
		} catch (GuzzleException $e) {
			$this->logger->error('Failed to start container', ['exception' => $e]);
			error_log($e->getMessage());
			return ['error' => 'Failed to start container'];
		}
	}

	public function pullContainer(string $imageName, string $imageTag = 'latest'): array {
		$url = $this->buildApiUrl(sprintf('images/create?fromImage=%s&tag=%s', urlencode($imageName), urlencode($imageTag)));
		try {
			$response = $this->guzzleClient->post($url);
			return ['success' => $response->getStatusCode() === 200];
		} catch (GuzzleException $e) {
			$this->logger->error('Failed to pull image', ['exception' => $e]);
			error_log($e->getMessage());
			return ['error' => 'Failed to pull image'];
		}
	}

	public function inspectContainer(string $containerId): array {
		$url = $this->buildApiUrl(sprintf('containers/%s/json', $containerId));
		try {
			$response = $this->guzzleClient->get($url);
			return json_decode((string) $response->getBody(), true);
		} catch (GuzzleException $e) {
			$this->logger->error('Failed to inspect container', ['exception' => $e]);
			error_log($e->getMessage());
			return ['error' => 'Failed to inspect container'];
		}
	}
}
