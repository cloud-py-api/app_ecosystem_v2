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

namespace OCA\AppEcosystemV2\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

class ExAppScopeMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'ex_apps_scopes');
	}

	public function findAll(int $limit = null, int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * @param string $appId
	 * @param string $scopeGroup
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 *
	 * @return ExAppScope
	 */
	public function findByAppidScope(string $appId, int $scopeGroup): ?Entity {
		$qb = $this->db->getQueryBuilder();
		return $this->findEntity($qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('appid', $qb->createNamedParameter($appId), IQueryBuilder::PARAM_STR))
			->andWhere($qb->expr()->eq('scope_group', $qb->createNamedParameter($scopeGroup), IQueryBuilder::PARAM_INT))
		);
	}
}
