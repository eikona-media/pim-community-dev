<?php

declare(strict_types=1);

namespace AkeneoTest\Pim\Enrichment\Integration\ProductQueryBuilder\Filter;

use Doctrine\DBAL\Connection;

class FindChildrenCategories
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function execute(string $categoryCode, array $categories): array
    {
        $query = <<<SQL
SELECT *
FROM pim_catalog_category as child
WHERE child.lft > (
		SELECT lft
		FROM pim_catalog_category
		WHERE code = :categoryCode
	)
	AND child.rgt < (
		SELECT rgt
		FROM pim_catalog_category
		WHERE code = :categoryCode
	)
AND child.id IN (:categories)
SQL;
        $statement = $this->connection->executeQuery($query, [$categoryCode, $categories]);
        $result = $statement->fetchAll();

        return $result;
    }
}
