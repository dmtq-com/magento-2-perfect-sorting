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
        $requestOrder = $this->_request->getParam("product_list_order");
        $catalogPage = $subject->getRequest()->getModuleName() == 'catalog';

        if (in_array($requestOrder, [Config::NEWEST_SORTING_SORT_KEY, Config::BEST_SELLERS_SORTING_SORT_KEY])) {
            $subject->setDefaultDirection('desc');
        }

        //$catId = (int)$this->_request->getParam("id");
        $catPath = $subject->getRequest()->getUri()->getPath();

        if (!$requestOrder && $catalogPage) {
            if ($catPath == '/new-arrivals.html') {
                $subject->setDefaultOrder(Config::NEWEST_SORTING_SORT_KEY);
                $subject->setDefaultDirection('desc');
            }
            if ($catPath == '/best-sellers.html') {
                $subject->setDefaultOrder(Config::BEST_SELLERS_SORTING_SORT_KEY);
                $subject->setDefaultDirection('desc');
            }
        }

        $collection->setOrder($subject->getCurrentOrder(), $subject->getCurrentDirection())->setOrder(Config::NEWEST_SORTING_SORT_KEY, 'desc');

        return $proceed($collection);
    }
}
