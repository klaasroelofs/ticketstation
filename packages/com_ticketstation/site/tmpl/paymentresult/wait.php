<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

$app      = Factory::getApplication();
$document = $app->getDocument();
$document->addStyleSheet('components/com_ticketstation/assets/css/component.css');
$document->setTitle(Text::_('COM_TICKETSTATION_PAYMENTRESULT_CHECKING_PAGE_TITLE') . ' - ' . $app->get('sitename'));

$orderCode = (int) $this->orderCode;
$itemid    = TicketstationFunctions::getSiteItemid();
$pollUrl   = Uri::root() . 'index.php?option=com_ticketstation&task=paymentresult.poll&format=json&ordercode=' . $orderCode;
$resultUrl = Route::_('index.php?option=com_ticketstation&view=paymentresult&ordercode=' . $orderCode . ($itemid ? '&Itemid=' . $itemid : ''), false);

## Contact address shown when the check takes longer than usual (Configuration > Company)
$contactEmail = htmlspecialchars($this->contactEmail, ENT_QUOTES, 'UTF-8');
$contactLink  = '<a href="mailto:' . $contactEmail . '">' . $contactEmail . '</a>';

?>

<div class="ticketstation ticketstation--paymentresult ticketstation--paymentresult-wait">
    <section class="ts-card ts-wait" role="status" aria-live="polite">
        <div class="ts-spinner" aria-hidden="true"></div>
        <h1 class="ts-wait__title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_CHECKING'); ?></h1>
        <p class="ts-wait__text"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_PLEASE_WAIT'); ?></p>
        <div class="ts-alert ts-wait__slow" id="ts-wait-slow" hidden>
            <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_TAKING_LONG', $contactLink); ?></p>
        </div>
    </section>
</div>

<script>
    (function () {
        const pollUrl = <?php echo json_encode($pollUrl, JSON_UNESCAPED_SLASHES); ?>;
        const resultUrl = <?php echo json_encode($resultUrl, JSON_UNESCAPED_SLASHES); ?>;
        const startedAt = Date.now();

        function pollStatus() {
            fetch(pollUrl, {cache: 'no-store'})
                .then(res => res.json())
                .then(data => {
                    if (data.processed === true) {
                        window.location.href = resultUrl;
                    } else {
                        nextPoll(1000);
                    }
                })
                .catch(() => nextPoll(1500));
        }

        function nextPoll(delay) {
            // After 20 seconds, reassure the customer and poll a bit less often
            if (Date.now() - startedAt > 20000) {
                document.getElementById('ts-wait-slow').hidden = false;
                delay = 3000;
            }

            setTimeout(pollStatus, delay);
        }

        setTimeout(pollStatus, 1000);
    })();
</script>
