<?php

namespace DMTQ\PerfectSorting\Plugin\Product\ProductList;

use Magento\Catalog\Block\Product\ProductList\Toolbar as ProductData;
use Magento\Framework\App\RequestInterface;
use DMTQ\PerfectSorting\Plugin\Model\Config;

class Toolbar
{
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

    public function aroundSetCollection(ProductData $subject, \Closure $proceed, $collection)
    {
        if ($this->_request->getParam("product_list_order") === Config::NEWEST_SORTING_SORT_KEY) {
            $subject->setDefaultDirection('desc');
        }

        $collection->setOrder($subject->getCurrentOrder(), $subject->getCurrentDirection())->setOrder(Config::NEWEST_SORTING_SORT_KEY, 'desc');

        return $proceed($collection);
    }
}
