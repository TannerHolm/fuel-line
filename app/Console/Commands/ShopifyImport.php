<?php

namespace App\Console\Commands;

use App\Enums\PipelineStage;
use App\Models\Account;
use App\Models\Order;
use App\Services\ShopifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One-way pull of real B2B history from Shopify into Fuel Line (spec §9).
 * Idempotent: customers match on shopify_customer_id → email → business name;
 * orders match on shopify_order_id. Safe to re-run any time.
 */
class ShopifyImport extends Command
{
    protected $signature = 'fuelline:shopify-import
        {--customers : Import from Customers instead of B2B Companies (fallback for stores without Companies)}
        {--all : Customers mode: import every customer, not just those tagged as wholesale}
        {--dry-run : Report what would change without writing anything}
        {--min-units= : Customers mode: only import customers whose largest order meets this unit count}
        {--emails= : Comma-separated emails to import, ignoring every other filter}
        {--exclude= : Comma-separated company names to skip (e.g. test records)}';

    protected $description = 'Import wholesale partners and their orders from Shopify';

    private int $accountsCreated = 0;

    private int $accountsLinked = 0;

    private int $ordersImported = 0;

    public function handle(ShopifyService $shopify): int
    {
        if (! $shopify->configured()) {
            $this->error('Shopify is not configured. Add SHOPIFY_SHOP_DOMAIN and SHOPIFY_ADMIN_TOKEN to .env first.');

            return self::FAILURE;
        }

        if (! $this->option('customers')) {
            return $this->importCompanies($shopify);
        }

        $emails = collect(explode(',', (string) $this->option('emails')))
            ->map(fn ($e) => mb_strtolower(trim($e)))
            ->filter()
            ->all();
        $minUnits = $this->option('min-units') !== null ? (int) $this->option('min-units') : null;

        // An explicit email list or a unit threshold selects locally; otherwise
        // fall back to Shopify's own tag filter.
        $query = ($emails || $minUnits !== null || $this->option('all'))
            ? null
            : 'tag:'.config('services.shopify.wholesale_tag');

        $cursor = null;
        $skipped = 0;

        do {
            $page = $shopify->fetchCustomersPage($query, $cursor);

            foreach ($page['customers'] as $customer) {
                if (! $this->selected($customer, $emails, $minUnits)) {
                    $skipped++;

                    continue;
                }

                $this->importCustomer($customer);
            }

            $cursor = $page['endCursor'];
        } while ($page['hasNextPage']);

        if ($skipped > 0) {
            $this->line("Skipped {$skipped} customers that did not match the filter.");
        }

        $mode = $this->option('dry-run') ? ' (dry run — nothing written)' : '';
        $this->info("Done{$mode}: {$this->accountsCreated} accounts created, {$this->accountsLinked} linked, {$this->ordersImported} orders imported.");

        if (! $this->option('dry-run') && $this->accountsCreated > 0) {
            $this->line('Run `php artisan fuelline:geocode` to pin the new accounts on the map.');
        }

        return self::SUCCESS;
    }

    /**
     * Companies path: every B2B Company is a wholesale account, including ones
     * with no orders yet — a company created today is a partner being set up,
     * which belongs on the pipeline board immediately.
     */
    private function importCompanies(ShopifyService $shopify): int
    {
        $exclude = collect(explode(',', (string) $this->option('exclude')))
            ->map(fn ($n) => mb_strtolower(trim($n)))
            ->filter()
            ->all();

        $emails = collect(explode(',', (string) $this->option('emails')))
            ->map(fn ($e) => mb_strtolower(trim($e)))
            ->filter()
            ->all();

        $cursor = null;
        $skipped = 0;

        do {
            $page = $shopify->fetchCompaniesPage($cursor);

            foreach ($page['companies'] as $company) {
                $contactEmail = mb_strtolower((string) ($company['mainContact']['customer']['email'] ?? ''));

                if (in_array(mb_strtolower($company['name']), $exclude, true)
                    || ($emails && ! in_array($contactEmail, $emails, true))) {
                    $skipped++;
                    $this->line("- skipped: {$company['name']}");

                    continue;
                }

                $this->importCompany($company);
            }

            $cursor = $page['endCursor'];
        } while ($page['hasNextPage']);

        $mode = $this->option('dry-run') ? ' (dry run — nothing written)' : '';
        $this->info("Done{$mode}: {$this->accountsCreated} accounts created, {$this->accountsLinked} updated, {$this->ordersImported} orders imported.".
            ($skipped ? " {$skipped} skipped." : ''));

        if (! $this->option('dry-run')) {
            $this->line('Run `php artisan fuelline:geocode` to pin any new accounts on the map.');
        }

        return self::SUCCESS;
    }

    private function importCompany(array $company): void
    {
        $companyId = (string) Str::afterLast($company['id'], '/');
        $contact = $company['mainContact']['customer'] ?? [];
        $email = $contact['email'] ?? null;
        $contactName = trim(($contact['firstName'] ?? '').' '.($contact['lastName'] ?? '')) ?: null;

        $location = $company['locations']['nodes'][0] ?? [];
        $address = $location['shippingAddress'] ?? $location['billingAddress'] ?? [];
        $city = $address['city'] ?? null;
        $state = $address['zoneCode'] ?? null;
        $phone = ($contact['phone'] ?? null) ?: (($location['phone'] ?? null) ?: ($address['phone'] ?? null));

        $account = Account::where('shopify_company_id', $companyId)->first()
            ?? ($email ? Account::whereRaw('lower(email) = ?', [mb_strtolower($email)])->first() : null)
            ?? Account::whereRaw('lower(name) = ?', [mb_strtolower($company['name'])])->first();

        $isNew = $account === null;
        $this->{$isNew ? 'accountsCreated' : 'accountsLinked'}++;
        $this->line(($isNew ? '+ account: ' : '~ updated: ').$company['name'].($email ? " <{$email}>" : ''));

        if ($this->option('dry-run')) {
            $hasOpening = $account?->orders()->where('type', 'opening')->exists() ?? false;

            foreach (collect($company['orders']['nodes'] ?? [])->sortBy('createdAt') as $o) {
                if (Order::where('shopify_order_id', (string) $o['legacyResourceId'])->exists()) {
                    continue; // already in Fuel Line — a preview shouldn't claim otherwise
                }

                $this->ordersImported++;
                $this->line(sprintf('  + %s order %s on %s — %s units, $%s',
                    $hasOpening ? 'reorder' : 'opening', $o['name'], substr($o['createdAt'], 0, 10),
                    $o['subtotalLineItemsQuantity'], $o['currentSubtotalPriceSet']['shopMoney']['amount'] ?? '0'));
                $hasOpening = true;
            }

            return;
        }

        if ($isNew) {
            $account = Account::create([
                'name' => $company['name'],
                'city' => $city,
                'state' => $state,
                'decision_maker' => $contactName,
                'phone' => $phone,
                'email' => $email,
                'lead_source' => 'other',
                'notes' => trim("Imported from Shopify B2B\n".($company['note'] ?? '')),
                'shopify_company_id' => $companyId,
                'shopify_customer_id' => $contact['legacyResourceId'] ?? null,
            ]);
        } else {
            // Shopify owns company identity, so the company name wins for
            // Shopify-sourced accounts. Everything else fills blanks only.
            $shopifySourced = $account->shopify_company_id !== null || $account->shopify_customer_id !== null;

            $account->forceFill(array_filter([
                'shopify_company_id' => $companyId,
                'shopify_customer_id' => $account->shopify_customer_id ?: ($contact['legacyResourceId'] ?? null),
                'name' => $shopifySourced ? $company['name'] : $account->name,
                'city' => $account->city ?: $city,
                'state' => $account->state ?: $state,
                'phone' => $account->phone ?: $phone,
                'decision_maker' => $account->decision_maker ?: $contactName,
                'email' => $account->email ?: $email,
            ]))->saveQuietly();
        }

        $this->importOrders($account, collect($company['orders']['nodes'] ?? []));
        $this->advanceStage($account->fresh());
    }

    /** Shared by both import paths. */
    private function importOrders(Account $account, \Illuminate\Support\Collection $orders): void
    {
        $hasOpening = $account->orders()->where('type', 'opening')->exists();

        foreach ($orders->sortBy('createdAt')->values() as $shopifyOrder) {
            $orderId = (string) $shopifyOrder['legacyResourceId'];

            if (Order::where('shopify_order_id', $orderId)->exists()) {
                $hasOpening = true;

                continue;
            }

            $this->ordersImported++;
            $this->line("  + order {$shopifyOrder['name']} ({$shopifyOrder['subtotalLineItemsQuantity']} units)");

            $quantity = max((int) $shopifyOrder['subtotalLineItemsQuantity'], 1);
            $revenue = (float) ($shopifyOrder['currentSubtotalPriceSet']['shopMoney']['amount'] ?? 0);
            $fulfilledAt = collect($shopifyOrder['fulfillments'] ?? [])->min('createdAt');

            $account->orders()->create([
                'type' => $hasOpening ? 'reorder' : 'opening',
                'date' => Carbon::parse($shopifyOrder['createdAt'])->toDateString(),
                'quantity' => $quantity,
                'tier' => \App\Models\PricingTier::forQuantity($quantity)?->key,
                'unit_price' => round($revenue / $quantity, 2),
                'revenue' => $revenue,
                'payment_status' => $shopifyOrder['displayFinancialStatus'] === 'PAID' ? 'paid' : 'invoiced',
                'fulfilled_at' => $shopifyOrder['displayFulfillmentStatus'] === 'FULFILLED'
                    ? Carbon::parse($fulfilledAt ?? $shopifyOrder['createdAt'])->toDateString()
                    : null,
                'shopify_order_id' => $orderId,
                'notes' => 'Imported from Shopify ('.$shopifyOrder['name'].')',
            ]);

            $hasOpening = true;
        }
    }

    /**
     * A named email always wins. Otherwise, a customer qualifies as wholesale
     * when any single order meets the minimum wholesale quantity — which is
     * what separates a retailer stocking a shelf from a consumer buying a puck.
     */
    private function selected(array $customer, array $emails, ?int $minUnits): bool
    {
        if ($emails) {
            return in_array(mb_strtolower((string) ($customer['email'] ?? '')), $emails, true);
        }

        if ($minUnits === null) {
            return true;
        }

        return collect($customer['orders']['nodes'] ?? [])
            ->contains(fn ($o) => (int) $o['subtotalLineItemsQuantity'] >= $minUnits);
    }

    private function importCustomer(array $customer): void
    {
        $shopifyId = (string) $customer['legacyResourceId'];
        $email = $customer['email'] ?? null;
        $contactName = trim(($customer['firstName'] ?? '').' '.($customer['lastName'] ?? '')) ?: null;

        // Wholesale buyers rarely fill in a company on their customer profile,
        // but they ship to a business — so the order's shipping address is the
        // best source for the store's real name and location.
        $ship = collect($customer['orders']['nodes'] ?? [])
            ->sortByDesc('createdAt')
            ->pluck('shippingAddress')
            ->filter()
            ->first() ?? [];

        $default = $customer['defaultAddress'] ?? [];

        $company = ($default['company'] ?? null) ?: ($ship['company'] ?? null);
        $city = ($default['city'] ?? null) ?: ($ship['city'] ?? null);
        $state = ($default['provinceCode'] ?? null) ?: ($ship['provinceCode'] ?? null);
        $phone = ($customer['phone'] ?? null) ?: ($ship['phone'] ?? null);

        // Fall back to the email domain before a person's name — "fasteddys"
        // identifies the account better than "Zach" on a pipeline board.
        $businessName = $company
            ?: ($email ? Str::of(Str::before(Str::after($email, '@'), '.'))->replace(['-', '_'], ' ')->title()->toString() : null)
            ?: ($contactName ?: "Shopify customer {$shopifyId}");

        $account = Account::where('shopify_customer_id', $shopifyId)->first()
            ?? ($email ? Account::whereRaw('lower(email) = ?', [mb_strtolower($email)])->first() : null)
            ?? Account::whereRaw('lower(name) = ?', [mb_strtolower($businessName)])->first();

        if ($account === null) {
            $this->accountsCreated++;
            $this->line("+ account: {$businessName}".($email ? " <{$email}>" : ''));

            if ($this->option('dry-run')) {
                // Preview the orders too — a dry run that hides them isn't a preview.
                foreach (collect($customer['orders']['nodes'] ?? [])->sortBy('createdAt') as $i => $o) {
                    $this->ordersImported++;
                    $this->line(sprintf('  + %s order %s on %s — %s units, $%s',
                        $i === 0 ? 'opening' : 'reorder',
                        $o['name'],
                        substr($o['createdAt'], 0, 10),
                        $o['subtotalLineItemsQuantity'],
                        $o['currentSubtotalPriceSet']['shopMoney']['amount'] ?? '0'));
                }

                return;
            }

            $account = Account::create([
                'name' => $businessName,
                'city' => $city,
                'state' => $state,
                'decision_maker' => $contactName,
                'phone' => $phone,
                'email' => $email,
                'lead_source' => 'other',
                'notes' => 'Imported from Shopify',
                'shopify_customer_id' => $shopifyId,
            ]);
        } else {
            $this->accountsLinked++;

            if (! $this->option('dry-run')) {
                // Fill blanks only — never overwrite what a founder typed.
                $account->forceFill(array_filter([
                    'shopify_customer_id' => $account->shopify_customer_id ?: $shopifyId,
                    'city' => $account->city ?: $city,
                    'state' => $account->state ?: $state,
                    'phone' => $account->phone ?: $phone,
                    'decision_maker' => $account->decision_maker ?: $contactName,
                    'email' => $account->email ?: $email,
                ]))->saveQuietly();
            }
        }

        $orders = collect($customer['orders']['nodes'] ?? [])->sortBy('createdAt')->values();
        $hasOpening = $account->orders()->where('type', 'opening')->exists();

        foreach ($orders as $i => $shopifyOrder) {
            $orderId = (string) $shopifyOrder['legacyResourceId'];

            if ($account->orders()->where('shopify_order_id', $orderId)->exists()) {
                $hasOpening = true;

                continue;
            }

            $this->ordersImported++;
            $this->line("  + order {$shopifyOrder['name']} ({$shopifyOrder['subtotalLineItemsQuantity']} units)");

            if ($this->option('dry-run')) {
                continue;
            }

            $quantity = (int) $shopifyOrder['subtotalLineItemsQuantity'];
            $revenue = (float) ($shopifyOrder['currentSubtotalPriceSet']['shopMoney']['amount'] ?? 0);
            $fulfilledAt = collect($shopifyOrder['fulfillments'] ?? [])->min('createdAt');

            $account->orders()->create([
                'type' => $hasOpening ? 'reorder' : 'opening',
                'date' => Carbon::parse($shopifyOrder['createdAt'])->toDateString(),
                'quantity' => max($quantity, 1),
                'tier' => \App\Models\PricingTier::forQuantity(max($quantity, 1))?->key,
                'unit_price' => $quantity > 0 ? round($revenue / $quantity, 2) : null,
                'revenue' => $revenue,
                'payment_status' => $shopifyOrder['displayFinancialStatus'] === 'PAID' ? 'paid' : 'invoiced',
                'fulfilled_at' => $shopifyOrder['displayFulfillmentStatus'] === 'FULFILLED'
                    ? Carbon::parse($fulfilledAt ?? $shopifyOrder['createdAt'])->toDateString()
                    : null,
                'shopify_order_id' => $orderId,
                'notes' => 'Imported from Shopify ('.$shopifyOrder['name'].')',
            ]);

            $hasOpening = true;
        }

        if (! $this->option('dry-run')) {
            $this->advanceStage($account->fresh());
        }
    }

    /**
     * Infer the pipeline stage from real order history. Only ever advances —
     * never regresses a stage a founder set deliberately.
     */
    private function advanceStage(Account $account): void
    {
        $reorders = $account->orders()->where('type', 'reorder')->count();
        $opening = $account->orders()->where('type', 'opening')->first();

        $target = match (true) {
            $reorders >= 2 => PipelineStage::RepeatAccount,
            $reorders === 1 => PipelineStage::Reordered,
            $opening?->fulfilled_at !== null => PipelineStage::Selling,
            $opening !== null => PipelineStage::OpeningOrder,
            default => null,
        };

        if ($target === null) {
            return;
        }

        $order = collect(PipelineStage::ordered());
        $currentIdx = $order->search($account->pipeline_stage);
        $targetIdx = $order->search($target);

        if ($account->pipeline_stage !== PipelineStage::Lost && $targetIdx > $currentIdx) {
            $account->transitionNote = 'Inferred from imported Shopify order history';
            $account->update(['pipeline_stage' => $target]);
        }
    }
}
