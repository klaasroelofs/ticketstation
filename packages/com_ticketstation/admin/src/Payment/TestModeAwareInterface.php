<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Payment;

defined('_JEXEC') or die;

/**
 * A provider whose payment service has a test environment (test keys, a sandbox address).
 *
 * Test mode is one switch for the whole shop, on the control panel. Ticketstation passes its state
 * to every plugin in the CollectProvidersEvent (CollectProvidersEvent::isTestMode()), and a plugin
 * hands it to its provider. The provider then uses the test environment exactly while the shop is in
 * test mode, and the live one otherwise. The admin has no separate switch to forget.
 *
 * A provider that does not implement this interface has no test environment. While the shop is in
 * test mode Ticketstation does not send paid orders to such a provider, as that would be real money.
 */
interface TestModeAwareInterface
{
    /**
     * Whether the provider uses its test environment now: the answer must be what the plugin got
     * from CollectProvidersEvent::isTestMode(). Ticketstation checks this before it starts a
     * payment, so a provider that ignores the event is refused rather than charging real money in
     * test mode.
     */
    public function isTestMode(): bool;
}
