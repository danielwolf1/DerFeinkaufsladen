<?php declare(strict_types=1);

namespace Fkl\Subscriber;

use Fkl\Struct\OptionDiscountData;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfiguratorOptionDiscountSubscriber implements EventSubscriberInterface
{
    public const EXTENSION_NAME = 'fklOptionDiscounts';

    /**
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $product = $page->getProduct();
        $configuratorSettings = $page->getConfiguratorSettings();

        if ($configuratorSettings === null || $configuratorSettings->count() === 0) {
            return;
        }

        $parentId = $product->getParentId() ?? $product->getId();

        $variants = $this->loadAllVariants($parentId, $event->getSalesChannelContext());

        if ($variants->count() === 0) {
            return;
        }

        $optionGroupCount = $configuratorSettings->count();
        $discountMap = $this->buildDiscountMap($variants, $optionGroupCount);

        if (empty($discountMap)) {
            return;
        }

        $optionDiscountData = new OptionDiscountData($discountMap, $optionGroupCount);

        $page->addExtension(self::EXTENSION_NAME, $optionDiscountData);
    }

    private function loadAllVariants(string $parentId, SalesChannelContext $context): ProductCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->setLimit(500);

        $result = $this->productRepository->search($criteria, $context);

        /** @var ProductCollection $products */
        $products = $result->getEntities();

        return $products;
    }

    /**
     * @return array<string, array{percentage: float, isExact: bool}>
     */
    private function buildDiscountMap(ProductCollection $variants, int $optionGroupCount): array
    {
        $optionDiscounts = [];

        /** @var SalesChannelProductEntity $variant */
        foreach ($variants as $variant) {
            $calculatedPrice = $variant->getCalculatedPrice();
            if ($calculatedPrice === null) {
                continue;
            }

            $listPrice = $calculatedPrice->getListPrice();
            if ($listPrice === null) {
                continue;
            }

            $percentage = $listPrice->getPercentage();
            if ($percentage <= 0) {
                continue;
            }

            $optionIds = $variant->getOptionIds();
            if ($optionIds === null) {
                continue;
            }

            foreach ($optionIds as $optionId) {
                if (!isset($optionDiscounts[$optionId])) {
                    $optionDiscounts[$optionId] = [];
                }
                $optionDiscounts[$optionId][] = $percentage;
            }
        }

        $discountMap = [];

        foreach ($optionDiscounts as $optionId => $percentages) {
            if (empty($percentages)) {
                continue;
            }

            $uniquePercentages = array_unique($percentages);
            $maxPercentage = max($percentages);

            // isExact: true if single option group OR all variants have same percentage
            $isExact = $optionGroupCount === 1 || count($uniquePercentages) === 1;

            $discountMap[$optionId] = [
                'percentage' => $maxPercentage,
                'isExact' => $isExact,
            ];
        }

        return $discountMap;
    }
}
