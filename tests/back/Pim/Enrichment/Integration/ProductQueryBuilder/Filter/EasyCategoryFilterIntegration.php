<?php

declare(strict_types=1);

namespace AkeneoTest\Pim\Enrichment\Integration\ProductQueryBuilder\Filter;

use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators;
use Akeneo\Pim\Structure\Component\AttributeTypes;
use Akeneo\Tool\Component\StorageUtils\Cursor\CursorInterface;
use AkeneoTest\Pim\Enrichment\Integration\PQB\AbstractProductQueryBuilderTestCase;

/**
 *
 * Category tree:
 *
 * master
 * |
 * +-----> collection-2018
 * |               |
 * |               +------> winter-2018
 * +-----> shoes
 *           |
 *           +-----> men
 *           +-----> women
 *
 * Dataset:
 *
 *                       +----+
 *                       |    | model-shoe
 *                       |    | category: "shoes"
 *                       +----+
 *                      X      X
 *                     X        X
 *                    X          X
 *                   X            X
 *             +----+             +----+
 *     model-s |    |             |    | model-m
 *             |    |             |    | category: "collection-2018"
 *             +----+             +----+
 *            X      X           X      X
 *           X        X         X        X
 *          X          X       X          X
 *         X            X     X            X
 *        X              X   X              X
 *  +----+          +----+   +----+         +----+          +-----+                       +------+
 *  |    |          |    |   |    |         |    |          |     |                       |      |
 *  |    |          |    |   |    |         |    |          |     |                       |      |
 *  +----+          +----+   +----+         +----+          +-----+                       +------+
 *  red-s          blue-s     red-m         blue-m         another-shoe                   unclassified-product
 *  category:      category:  category:     category:      category: "women,winter-2018"
 *  "women"        "men"      "women"       "men"
 *
 * Tests of the filter category used in the UI. The search results need be aggregated towards higher product model level
 * as much as possible.
 *
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class EasyCategoryFilterIntegration extends AbstractProductQueryBuilderTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createCategoryTree();
        $this->createStructure();
        $this->createDataset();

        $this->get('akeneo_elasticsearch.client.product_and_product_model')->refreshIndex();
    }

    /**
     * @test
     */
    public function Master_and_CHILDREN_But_NOT_B_and_children(): void
    {
        $result = $this->executeFilter(
            [
                ['categories', Operators::IN_CHILDREN_LIST_WITH_UNSELECTION, ['master']],
                ['categories', Operators::NOT_IN_CHILDREN_LIST, ['B']]
            ]);
        $this->assert($result, ['PA', 'PD']);
    }

    /**
     * @test
     */
    public function Master_and_CHILDREN_But_NOT_B_and_children_BUT_C_AND_CHILDREN(): void
    {
        $findChildrenCategories = new FindChildrenCategories($this->get('doctrine.dbal.connection'));

        $result = $this->executeFilter(
            [
                ['categories', Operators::IN_CHILDREN_LIST_WITH_UNSELECTION, [['master', 'C'], ['B']]],
            ]);
        $this->assert($result, ['PA', 'PD', 'PC']);
    }

    /**
     * Creates the category tree.
     *
     * See the class PhpDoc for more info.
     */
    private function createCategoryTree(): void
    {
        $this->createCategory(['code' => 'B', 'parent' => 'master']);
        $this->createCategory(['code' => 'D', 'parent' => 'master']);
        $this->createCategory(['code' => 'C', 'parent' => 'B']);
    }

    /**
     * Creates all the products and product models.
     *
     * See the class PhpDoc for more info.
     */
    protected function createDataset(): void
    {
        $this->createProduct('PA', ['categories' => ['master']]);
        $this->createProduct('PB', ['categories' => ['B']]);
        $this->createProduct('PC', ['categories' => ['C']]);
        $this->createProduct('PD', ['categories' => ['D']]);
        $this->createProduct('unclassified-product', []);
    }

    /**
     * Creates a catalog structure (family, family variants, attributes)
     */
    private function createStructure(): void
    {
        // Attributes
        $this->createAttribute([
            'code'        => 'description',
            'type'        => AttributeTypes::TEXT,
            'localizable' => false,
            'scopable'    => false,
        ]);

        // color
        $this->createAttribute([
            'code'        => 'color',
            'type'        => AttributeTypes::OPTION_SIMPLE_SELECT,
            'localizable' => false,
            'scopable'    => false,
        ]);
        $this->createAttributeOption([
            'attribute' => 'color',
            'code'      => 'red'
        ]);
        $this->createAttributeOption([
            'attribute' => 'color',
            'code'      => 'blue'
        ]);

        // size
        $this->createAttribute([
            'code'        => 'size',
            'type'        => AttributeTypes::OPTION_SIMPLE_SELECT,
            'localizable' => false,
            'scopable'    => false,
        ]);
        $this->createAttributeOption([
            'attribute' => 'size',
            'code'      => 's'
        ]);
        $this->createAttributeOption([
            'attribute' => 'size',
            'code'      => 'm'
        ]);

        // Family & Family variant
        $this->createFamily([
            'code'                   => 'shoes',
            'attributes'             => ['sku', 'color', 'size', 'description'],
            'attribute_requirements' => [],
        ]);

        $this->createFamilyVariant([
            'code'                   => 'shoe_size_color',
            'family'                 => 'shoes',
            'variant_attribute_sets' => [
                [
                    'level'      => 1,
                    'axes'       => ['size'],
                    'attributes' => ['size'],
                ],
                [
                    'level'      => 2,
                    'axes'       => ['color'],
                    'attributes' => ['color'],
                ],
            ],
        ]);

        $this->get('akeneo_elasticsearch.client.product_and_product_model')->refreshIndex();
    }

    /**
     * @param array $data
     */
    private function createProductModel(array $data)
    {
        $productModel = $this->get('pim_catalog.factory.product_model')->create();
        $this->get('pim_catalog.updater.product_model')->update(
            $productModel,
            $data
        );

        $violations = $this->get('validator')->validate($productModel);
        $this->assertEquals(0, $violations->count());

        $this->get('pim_catalog.saver.product_model')->save($productModel);

        $this->get('akeneo_elasticsearch.client.product_and_product_model')->refreshIndex();
    }

    /**
     * @param string $identifier
     * @param array  $data
     */
    protected function createVariantProduct(string $identifier, array $data = [])
    {
        $product = $this->get('pim_catalog.builder.product')->createProduct($identifier);
        $this->get('pim_catalog.updater.product')->update($product, $data);
        $constraintList = $this->get('pim_catalog.validator.product')->validate($product);
        $this->assertEquals(0, $constraintList->count());
        $this->get('pim_catalog.saver.product')->save($product);
        $this->get('akeneo_elasticsearch.client.product_and_product_model')->refreshIndex();
    }

    /**
     * @param array $filters
     *
     * @return CursorInterface
     */
    protected function executeFilter(array $filters)
    {
        $pqb = $this->get('pim_catalog.query.product_query_builder_factory')->create();

        foreach ($filters as $filter) {
            $context = isset($filter[3]) ? $filter[3] : [];
            $pqb->addFilter($filter[0], $filter[1], $filter[2], $context);
        }

        return $pqb->execute();
    }

    /**
     * @param CursorInterface $result
     * @param array           $expected
     */
    protected function assert(CursorInterface $result, array $expected)
    {
        $entities = [];
        foreach ($result as $entity) {
            if ($entity instanceof ProductInterface) {
                $entities[] = $entity->getIdentifier();
            } elseif ($entity instanceof ProductModelInterface) {
                $entities[] = $entity->getCode();
            }
        }

        sort($entities);
        sort($expected);

        $this->assertSame($expected, $entities);
    }

    protected function getConfiguration()
    {
        return $this->catalog->useMinimalCatalog();
    }
}
