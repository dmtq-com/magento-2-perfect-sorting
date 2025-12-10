<?php

declare(strict_types=1);

namespace DMTQ\PerfectSorting\Model\Adapter\BatchDataMapper;

use DMTQ\PerfectSorting\Plugin\Model\Config;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\ResourceModel\Report\Bestsellers\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;


class CustomDataProvider implements AdditionalFieldsProviderInterface
{
    protected CollectionFactory $bestSellersCollectionFactory;
    protected StoreManagerInterface $storeManager;
    protected ProductRepository $productRepository;
    protected ResourceConnection $resourceConnection;
    protected DeploymentConfig $deploymentConfig;


    public function __construct(
        CollectionFactory     $bestSellersCollectionFactory,
        StoreManagerInterface $storeManager,
        ProductRepository     $productRepository,
        ResourceConnection    $resourceConnection,
        DeploymentConfig      $deploymentConfig
    )
    {
        $this->bestSellersCollectionFactory = $bestSellersCollectionFactory;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->resourceConnection = $resourceConnection;
        $this->deploymentConfig = $deploymentConfig;
    }


    /**
     * Add custom sorting field to data mapper used by elasticsearch
     *
     * @param array<int, int> $productIds
     * @return array<int, array<string, mixed>>
     * @throws NoSuchEntityException
     */
    public function getFields(array $productIds, $storeId): array
    {
        $fields = [];
        $besetSellersData = $this->getBestSellersData($storeId);
        $stockQtyData = $this->getStockQtyData($productIds);
        $discountRates = $this->getDiscountRates($productIds, $storeId);
        $priceData = $this->getPriceData($productIds, $storeId);
        $priceSortData = $this->sortPriceHighToLow($priceData);

        foreach ($productIds as $productId) {
            $fields[$productId] = [
                Config::NEWEST_SORTING_SORT_KEY => $productId,
                Config::BEST_SELLERS_SORTING_SORT_KEY => $besetSellersData[$productId] ?? 0,
                Config::STOCK_QTY_SORT_KEY => $stockQtyData[$productId] ?? 0,
                Config::DISCOUNT_SORT_KEY => $discountRates[$productId] ?? 0,
                Config::PRICE_LOW_TO_HIGH_SORT_KEY => $priceData[$productId] ?? 0,
                Config::PRICE_HIGH_TO_LOW_SORT_KEY => $priceSortData[$productId] ?? 0,
            ];
        }

        return $fields;
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getBestSellersData($storeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('sales_order_item');
        $bestsellersData = [];
        $select = $connection->select()
            ->from(
                $tableName,
                [
                    'store_id',
                    'product_id',
                    'total_qty' => new \Zend_Db_Expr('SUM(qty_ordered)')
                ]
            )
            ->where('store_id = ?', $storeId)
            ->where('created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)')
            ->group(['store_id', 'product_id']);

        $rows = $connection->fetchAll($select);
        foreach ($rows as $row) {
            $bestsellersData[$row['product_id']] = (int)$row['total_qty'];
        }
        return $bestsellersData;
    }


    /**
     * @param $productIds
     * @return array
     */
    public function getStockQtyData($productIds): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('cataloginventory_stock_item');

        $stockData = [];
        $batchSize = 300;
        $batches = array_chunk($productIds, $batchSize);

        foreach ($batches as $batch) {
            $select = $connection->select()
                ->from($tableName, ['product_id', 'qty'])
                ->where('product_id IN (?)', $batch);

            $rows = $connection->fetchAll($select);
            foreach ($rows as $row) {
                $qty = $row['qty'];
                $stockData[$row['product_id']] = (int)$qty;
            }
        }

        return $stockData;
    }

    /**
     * @param array $productIds
     * @param $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    protected function getDiscountRates(array $productIds, $storeId): array
    {
        $websiteID = $this->storeManager->getStore($storeId)->getWebsiteId();
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('catalog_product_index_price');

        $discountRates = [];
        $batchSize = 300;
        $batches = array_chunk($productIds, $batchSize);

        foreach ($batches as $batch) {
            $select = $connection->select()
                ->from($tableName, ['entity_id', 'price', 'final_price'])
                ->where('entity_id IN (?)', $batch)
                ->where('website_id = ?', $websiteID);

            $prices = $connection->fetchAll($select);
            foreach ($prices as $row) {
                $originalPrice = $row['price'];
                $finalPrice = $row['final_price'];
                $discountRate = 0;
                if ($originalPrice > 0) {
                    $discountRate = ($originalPrice - $finalPrice) / $originalPrice * 100;
                }
                $discountRates[$row['entity_id']] = (int)$discountRate;
            }
        }

        return $discountRates;
    }

    /**
     * @param array $productIds
     * @param $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    protected function getPriceData(array $productIds, $storeId): array
    {
        $websiteID = $this->storeManager->getStore($storeId)->getWebsiteId();
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('catalog_product_index_price');

        $priceData = [];
        $batchSize = 300;
        $batches = array_chunk($productIds, $batchSize);

        foreach ($batches as $batch) {
            $select = $connection->select()
                ->from($tableName, ['entity_id', 'price', 'min_price'])
                ->where('entity_id IN (?)', $batch)
                ->where('website_id = ?', $websiteID);

            $prices = $connection->fetchAll($select);
            foreach ($prices as $row) {
                $minPrice = $row['min_price'];
                $priceData[$row['entity_id']] = (int)$minPrice;
            }
        }

        return $priceData;
    }

    /**
     * @param $products
     * @return array
     */
    protected function sortPriceHighToLow($products): array
    {
        $sorted = [];
        foreach ($products as $id => $price) {
            $sorted[$id] = 1000000 - $price;
        }
        return $sorted;
    }
}


