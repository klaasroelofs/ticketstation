<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_stripe
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\TicketstationPayment\Stripe\Api;

defined('_JEXEC') or die;

use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentException;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderNotConfiguredException;

/**
 * The few calls of Stripe's REST API that the plugin needs, over Joomla's HTTP client. Parameters
 * are sent form-encoded, nested arrays as Stripe expects them (a[b][0]=c). The transport can be
 * replaced (a closure taking the method, URL, headers and body and returning [HTTP status, body]),
 * which is how the tests run without Stripe.
 */
final class StripeClient
{
    private const BASE = 'https://api.stripe.com/v1/';

    private string $secretKey;

    private ?\Closure $transport;

    public function __construct(string $secretKey, ?\Closure $transport = null)
    {
        $this->secretKey = $secretKey;
        $this->transport = $transport;
    }

    /**
     * @throws  StripeNotFoundException
     * @throws  ProviderNotConfiguredException
     * @throws  PaymentException
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path . ($query ? '?' . http_build_query($query) : ''), null);
    }

    /**
     * @throws  StripeNotFoundException
     * @throws  ProviderNotConfiguredException
     * @throws  PaymentException
     */
    public function post(string $path, array $params): array
    {
        return $this->request('POST', $path, http_build_query($params));
    }

    private function request(string $method, string $path, ?string $body): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->secretKey];

        if ($body !== null) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        try {
            [$status, $raw] = $this->transport
                ? ($this->transport)($method, self::BASE . $path, $headers, $body)
                : $this->send($method, self::BASE . $path, $headers, $body);
        } catch (\RuntimeException $e) {
            throw new PaymentException('Stripe could not be reached: ' . $e->getMessage(), 0, $e);
        }

        $data = json_decode($raw, true);

        if ($status >= 200 && $status < 300 && is_array($data)) {
            return $data;
        }

        $error   = is_array($data) ? ($data['error'] ?? []) : [];
        $message = (string) ($error['message'] ?? ('Stripe answered with HTTP ' . $status . '.'));

        if ($status === 401) {
            throw new ProviderNotConfiguredException(Text::_('PLG_TICKETSTATIONPAYMENT_STRIPE_ERROR_KEY_REFUSED'));
        }

        if ($status === 404 && ($error['code'] ?? '') === 'resource_missing') {
            throw new StripeNotFoundException($message);
        }

        throw new PaymentException($message);
    }

    /**
     * @return  array  [HTTP status, body]
     */
    private function send(string $method, string $url, array $headers, ?string $body): array
    {
        $http     = HttpFactory::getHttp();
        $response = $method === 'POST'
            ? $http->post($url, (string) $body, $headers, 30)
            : $http->get($url, $headers, 30);

        return [$response->getStatusCode(), (string) $response->getBody()];
    }
}
