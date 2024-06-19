<?php

namespace DMTQ\PerfectSorting\Plugin\Model;
use Magento\Framework\App\RequestInterface;

class Config
{
    /**
     * @var string
     */
    public const NEWEST_SORTING_SORT_KEY = 'newest';
    public const BEST_SELLERS_SORTING_SORT_KEY = 'best_sellers';
    public const STOCK_QTY_SORT_KEY = 'stock_qty';
    public const DISCOUNT_SORT_KEY = 'discount';

    public const PRICE_LOW_TO_HIGH_SORT_KEY = 'price_asc';
    public const PRICE_HIGH_TO_LOW_SORT_KEY = 'price_desc';


    /**
     * Request
     * @var RequestInterface
     */
    protected RequestInterface $_request;

    /**
     * Class constructor
     * @param RequestInterface $request
     */
    public function __construct(
        RequestInterface $request
    )
    {
        $this->_request = $request;
    }

    public function afterGetAttributeUsedForSortByArray(\Magento\Catalog\Model\Config $catalogConfig, $options): array
    {
        $options['position'] = __('Recommended');
        $customOption[self::NEWEST_SORTING_SORT_KEY] = __('Newest');
        $customOption[self::BEST_SELLERS_SORTING_SORT_KEY] = __('Best Sellers');
        // $customOption[self::STOCK_QTY_SORT_KEY] = __('Stock Quantity');
        // $customOption[self::DISCOUNT_SORT_KEY] = __('Discount');
        $customOption[self::PRICE_LOW_TO_HIGH_SORT_KEY] = __('Price Low to High');
        $customOption[self::PRICE_HIGH_TO_LOW_SORT_KEY] = __('Price High to Low');
        unset($options['name']);
        unset($options['price']);
        return array_merge($options,$customOption);
    }
}
