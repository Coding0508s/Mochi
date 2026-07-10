<?php

namespace App\Support;

use App\Repositories\Store\StoreReturnEcountProductRepository;
use App\Services\Store\EcountApiClient;
use Illuminate\Support\Facades\Cache;

final class StoreReturnEcountProductOptions
{
    public function __construct(
        private readonly StoreReturnProductCodeResolver $productCodeResolver,
        private readonly StoreReturnEcountProductRepository $productRepository,
        private readonly EcountApiClient $ecountApiClient,
    ) {}

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        $ttl = max(0, (int) config('store.return_registration.ecount_cache_ttl_seconds', 120));
        $cacheKey = (string) config('store.return_registration.ecount_cache_prefix', 'store_return').':ecount_product_options';

        if ($ttl > 0) {
            return Cache::remember($cacheKey, $ttl, fn (): array => $this->buildOptions());
        }

        return $this->buildOptions();
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return array_values(array_map(
            static fn (array $option): string => $option['value'],
            $this->options(),
        ));
    }

    /**
     * @param  list<string>  $additionalValues
     * @return list<string>
     */
    public function allowedValues(array $additionalValues = []): array
    {
        $values = $this->values();
        if ($additionalValues === []) {
            return $values;
        }

        foreach ($additionalValues as $value) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $selection = $this->selectionValueForStoredItemName($trimmed);
            if ($selection !== '' && ! in_array($selection, $values, true)) {
                $values[] = $selection;
            }

            if (! in_array($trimmed, $values, true)) {
                $values[] = $trimmed;
            }
        }

        return $values;
    }

    public function displayNameForProductCode(string $productCode): string
    {
        $code = strtoupper(trim($productCode));
        if ($code === '') {
            return '';
        }

        foreach ($this->options() as $option) {
            if ($option['value'] === $code) {
                return $option['label'];
            }
        }

        return trim($productCode);
    }

    public function displayNameForStoredItemName(string $storedItemName): string
    {
        $stored = trim($storedItemName);
        if ($stored === '') {
            return '';
        }

        foreach ($this->options() as $option) {
            if ($option['label'] === $stored) {
                return $stored;
            }
        }

        return $this->displayNameForProductCode($stored);
    }

    public function selectionValueForStoredItemName(string $storedItemName): string
    {
        $stored = trim($storedItemName);
        if ($stored === '') {
            return '';
        }

        $normalizedStored = strtoupper($stored);

        foreach ($this->options() as $option) {
            if ($option['label'] === $stored || $option['value'] === $normalizedStored) {
                return $option['value'];
            }
        }

        return $stored;
    }

    /**
     * @param  array<int, string>  $productCodes
     * @return array<string, string>
     */
    public function resolveDisplayNamesForCodes(array $productCodes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            $productCodes,
        ), static fn (string $code): bool => $code !== '')));

        if ($codes === []) {
            return [];
        }

        return $this->resolveDisplayNameMap($codes);
    }

    public function forgetCachedOptions(): void
    {
        $cacheKey = (string) config('store.return_registration.ecount_cache_prefix', 'store_return').':ecount_product_options';
        Cache::forget($cacheKey);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function buildOptions(): array
    {
        if (! (bool) config('store.return_registration.ecount_enabled', true)) {
            return [];
        }

        $codes = $this->productCodeResolver->resolveProductCodes();
        if ($codes === []) {
            return [];
        }

        $nameMap = $this->resolveDisplayNameMap($codes);

        $options = [];
        foreach ($codes as $code) {
            $normalizedCode = strtoupper(trim($code));
            if ($normalizedCode === '') {
                continue;
            }

            $displayName = trim((string) ($nameMap[$normalizedCode] ?? ''));
            if ($displayName === '') {
                $displayName = $normalizedCode;
            }

            $options[] = [
                'value' => $normalizedCode,
                'label' => $displayName,
            ];
        }

        usort($options, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function resolveDisplayNameMap(array $codes): array
    {
        $dbNames = $this->productRepository->getProductNameMapByCodes($codes);

        $missingCodes = [];
        foreach ($codes as $code) {
            $normalizedCode = strtoupper(trim($code));
            if ($normalizedCode === '') {
                continue;
            }

            if (trim((string) ($dbNames[$normalizedCode] ?? '')) === '') {
                $missingCodes[] = $normalizedCode;
            }
        }

        $apiNames = $missingCodes !== []
            ? $this->ecountApiClient->fetchProductDisplayNamesByCodes($missingCodes)
            : [];

        $toBackfill = [];
        foreach ($apiNames as $code => $name) {
            $normalizedCode = strtoupper(trim((string) $code));
            $trimmedName = trim((string) $name);
            if ($normalizedCode === '' || $trimmedName === '') {
                continue;
            }

            if (trim((string) ($dbNames[$normalizedCode] ?? '')) !== '') {
                continue;
            }

            $toBackfill[$normalizedCode] = $trimmedName;
        }

        if ($toBackfill !== []) {
            $this->productRepository->backfillMissingProductNames($toBackfill);
            $this->forgetCachedOptions();
        }

        $map = [];
        foreach ($codes as $code) {
            $normalizedCode = strtoupper(trim($code));
            if ($normalizedCode === '') {
                continue;
            }

            $name = trim((string) ($dbNames[$normalizedCode] ?? $toBackfill[$normalizedCode] ?? $apiNames[$normalizedCode] ?? ''));
            if ($name !== '') {
                $map[$normalizedCode] = $name;
            }
        }

        return $map;
    }
}
