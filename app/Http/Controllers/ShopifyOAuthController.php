<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * One-time OAuth install for the Shopify dev-dashboard app.
 *
 * Two entry paths, both landing here:
 *   1. Founder-initiated — someone visits /shopify/connect in Fuel Line.
 *   2. Shopify-initiated — the custom-distribution install link opens the App
 *      URL with ?shop=&hmac=&timestamp=. There is no state yet in that case;
 *      we verify the HMAC, then start the handshake ourselves.
 *
 * The store that completes the install becomes the connected store, so the
 * .env domain is only a default.
 */
class ShopifyOAuthController extends Controller
{
    public function connect(Request $request): RedirectResponse
    {
        $clientId = config('services.shopify.client_id');
        $clientSecret = config('services.shopify.client_secret');

        abort_unless(filled($clientId) && filled($clientSecret), 400,
            'Set SHOPIFY_CLIENT_ID and SHOPIFY_CLIENT_SECRET in .env first.');

        // A Shopify-initiated install carries an HMAC — verify it. A founder
        // hitting this route directly may name the shop themselves (?shop=…),
        // which is how you pin the right store when several are in play.
        if ($request->has('hmac')) {
            abort_unless($this->hmacValid($request), 403, 'Install request failed HMAC verification.');
        }

        $domain = $request->filled('shop')
            ? $this->normalizeShop($request->query('shop'))
            : $this->normalizeShop(config('services.shopify.domain'));

        $state = Str::random(40);
        $request->session()->put('shopify_oauth_state', $state);
        $request->session()->put('shopify_oauth_shop', $domain);

        return redirect()->away("https://{$domain}/admin/oauth/authorize?".http_build_query([
            'client_id' => $clientId,
            'scope' => config('services.shopify.scopes'),
            'redirect_uri' => route('shopify.callback'),
            'state' => $state,
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('shopify_oauth_state');
        $expectedShop = $request->session()->pull('shopify_oauth_shop');

        abort_unless(filled($expectedState) && hash_equals($expectedState, (string) $request->query('state')), 403, 'OAuth state mismatch.');
        abort_unless($this->hmacValid($request), 403, 'OAuth HMAC verification failed.');

        $shop = $this->normalizeShop($request->query('shop'));

        // Shopify can redirect the authorize step to whichever store is active
        // in the browser, so name both shops when they diverge — otherwise this
        // is impossible to diagnose from the error alone.
        abort_unless($shop === $expectedShop, 403,
            "Shopify sent the callback for {$shop}, but the install started from {$expectedShop}. "
            ."Switch that store to be the active one in Shopify admin, or start over at "
            ."/shopify/connect?shop={$expectedShop}");

        $response = Http::asForm()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('services.shopify.client_id'),
            'client_secret' => config('services.shopify.client_secret'),
            'code' => $request->query('code'),
        ])->throw();

        AppSetting::put('shopify_admin_token', $response->json('access_token'));
        AppSetting::put('shopify_shop_domain', $shop);

        return redirect()->route('kpis')->with('success',
            "Shopify connected to {$shop}. Next: php artisan fuelline:shopify-import --dry-run");
    }

    private function normalizeShop(?string $shop): string
    {
        // Accept "store.myshopify.com" only — never an arbitrary host.
        $shop = mb_strtolower(trim((string) $shop));

        abort_unless(preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', $shop) === 1, 403, 'Invalid shop domain.');

        return $shop;
    }

    /** Shopify signs the query string with the app's client secret. */
    private function hmacValid(Request $request): bool
    {
        $params = $request->query();
        $hmac = $params['hmac'] ?? '';
        unset($params['hmac'], $params['signature']);
        ksort($params);

        $expected = hash_hmac('sha256', http_build_query($params), config('services.shopify.client_secret'));

        return hash_equals($expected, (string) $hmac);
    }
}
