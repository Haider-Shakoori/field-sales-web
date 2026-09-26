<?php

namespace App\Services\BusinessOs;

use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MasterDataImporter
{
    public function __construct(
        private readonly EntityLinkService $links,
    ) {}

    public function import(string $type, array $records): array
    {
        $counts = [
            'received' => count($records),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];
        $errors = [];

        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                $counts['failed']++;
                $errors[] = "Record {$index} is not an object.";

                continue;
            }

            try {
                $result = DB::transaction(
                    fn (): string => match ($type) {
                        'products' => $this->product($record),
                        'customers' => $this->customer($record),
                        'prices' => $this->priceList($record),
                        default => 'skipped',
                    }
                );
                $counts[$result] = ($counts[$result] ?? 0) + 1;
            } catch (Throwable $exception) {
                $counts['failed']++;
                $errors[] = sprintf(
                    '%s %s: %s',
                    $type,
                    (string) ($record['external_id'] ?? "#{$index}"),
                    $exception->getMessage(),
                );
            }
        }

        return [
            'counts' => $counts,
            'errors' => array_slice($errors, 0, 20),
        ];
    }

    private function product(array $record): string
    {
        $externalId = $this->required($record, 'external_id');
        $sku = $this->required($record, 'sku');
        $name = $this->required($record, 'name');
        $existing = $this->links->findLocal(
            'product',
            $externalId,
            Product::class,
        );
        $product = $existing instanceof Product
            ? $existing
            : Product::query()->where('sku', $sku)->first();
        $created = ! $product;

        $product ??= new Product;
        $product->fill([
            'sku' => $sku,
            'barcode' => $this->nullableString($record['barcode'] ?? null),
            'name' => $name,
            'description' => $this->nullableString(
                $record['description'] ?? null,
            ),
            'unit' => trim((string) ($record['unit'] ?? 'pcs')) ?: 'pcs',
            'base_price' => (float) ($record['base_price'] ?? 0),
            'currency' => strtoupper(
                trim((string) ($record['currency'] ?? 'AFN')),
            ) ?: 'AFN',
            'is_active' => (bool) ($record['is_active'] ?? true),
        ]);
        $product->save();

        $this->links->link(
            $product,
            'product',
            $externalId,
            $this->nullableString($record['version'] ?? null),
            'businessos',
            ['sku' => $sku],
        );

        return $created ? 'created' : 'updated';
    }

    private function customer(array $record): string
    {
        $externalId = $this->required($record, 'external_id');
        $code = $this->required($record, 'code');
        $name = $this->required($record, 'name');
        $existing = $this->links->findLocal(
            'customer',
            $externalId,
            Customer::class,
        );
        $customer = $existing instanceof Customer
            ? $existing
            : Customer::query()->where('code', $code)->first();
        $created = ! $customer;

        $customer ??= new Customer;
        $customer->fill([
            'code' => $code,
            'name' => $name,
            'contact_person' => $this->nullableString(
                $record['contact_person'] ?? null,
            ),
            'phone' => $this->nullableString($record['phone'] ?? null),
            'alternate_phone' => $this->nullableString(
                $record['alternate_phone'] ?? null,
            ),
            'email' => $this->nullableString($record['email'] ?? null),
            'address' => $this->nullableString($record['address'] ?? null),
            'latitude' => $this->nullableFloat($record['latitude'] ?? null),
            'longitude' => $this->nullableFloat($record['longitude'] ?? null),
            'geofence_radius_meters' => max(
                10,
                min(5000, (int) ($record['geofence_radius_meters'] ?? 100)),
            ),
            'is_active' => (bool) ($record['is_active'] ?? true),
        ]);

        $priceListExternalId = $this->nullableString(
            $record['price_list_external_id'] ?? null,
        );
        if ($priceListExternalId !== null) {
            $priceList = $this->links->findLocal(
                'price_list',
                $priceListExternalId,
                PriceList::class,
            );
            $customer->price_list_id = $priceList?->getKey();
        }

        $customer->save();

        $this->links->link(
            $customer,
            'customer',
            $externalId,
            $this->nullableString($record['version'] ?? null),
            'businessos',
            ['code' => $code],
        );

        return $created ? 'created' : 'updated';
    }

    private function priceList(array $record): string
    {
        $externalId = $this->required($record, 'external_id');
        $code = $this->required($record, 'code');
        $name = $this->required($record, 'name');
        $existing = $this->links->findLocal(
            'price_list',
            $externalId,
            PriceList::class,
        );
        $priceList = $existing instanceof PriceList
            ? $existing
            : PriceList::query()->where('code', $code)->first();
        $created = ! $priceList;

        $priceList ??= new PriceList;
        $priceList->fill([
            'code' => $code,
            'name' => $name,
            'currency' => strtoupper(
                trim((string) ($record['currency'] ?? 'AFN')),
            ) ?: 'AFN',
            'effective_from' => $record['effective_from'] ?? null,
            'effective_to' => $record['effective_to'] ?? null,
            'is_active' => (bool) ($record['is_active'] ?? true),
        ]);
        $priceList->save();

        $this->links->link(
            $priceList,
            'price_list',
            $externalId,
            $this->nullableString($record['version'] ?? null),
            'businessos',
            ['code' => $code],
        );

        foreach (($record['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $product = $this->productForPrice($item);
            if (! $product) {
                continue;
            }

            PriceListItem::query()->updateOrCreate(
                [
                    'price_list_id' => $priceList->id,
                    'product_id' => $product->id,
                    'min_quantity' => (float) ($item['min_quantity'] ?? 1),
                ],
                [
                    'price' => (float) ($item['price'] ?? 0),
                ],
            );
        }

        return $created ? 'created' : 'updated';
    }

    private function productForPrice(array $item): ?Product
    {
        $externalId = $this->nullableString(
            $item['product_external_id'] ?? null,
        );

        if ($externalId !== null) {
            $product = $this->links->findLocal(
                'product',
                $externalId,
                Product::class,
            );

            if ($product instanceof Product) {
                return $product;
            }
        }

        $sku = $this->nullableString($item['sku'] ?? null);

        return $sku === null
            ? null
            : Product::query()->where('sku', $sku)->first();
    }

    private function required(array $record, string $key): string
    {
        $value = trim((string) ($record[$key] ?? ''));

        if ($value === '') {
            throw new \InvalidArgumentException(
                "BusinessOS record is missing {$key}.",
            );
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
