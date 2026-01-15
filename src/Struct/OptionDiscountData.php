<?php declare(strict_types=1);

namespace Fkl\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OptionDiscountData extends Struct
{
    /**
     * Map of optionId => discount data
     * @var array<string, array{percentage: float, isExact: bool}>
     */
    protected array $discounts = [];

    protected int $optionGroupCount = 0;

    /**
     * @param array<string, array{percentage: float, isExact: bool}> $discounts
     */
    public function __construct(array $discounts = [], int $optionGroupCount = 0)
    {
        $this->discounts = $discounts;
        $this->optionGroupCount = $optionGroupCount;
    }

    /**
     * @return array{percentage: float, isExact: bool}|null
     */
    public function getDiscountForOption(string $optionId): ?array
    {
        return $this->discounts[$optionId] ?? null;
    }

    public function hasDiscount(string $optionId): bool
    {
        return isset($this->discounts[$optionId]);
    }
}
