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

Factory::getApplication()->getDocument()->addStyleSheet('components/com_ticketstation/assets/css/component.css');

$orderCode = $this->orderCode;
$itemid = TicketstationFunctions::getSiteItemid();
$pollUrl = Uri::root() . "index.php?option=com_ticketstation&task=paymentresult.poll&format=json";
//$resultUrl = Uri::root() . "index.php?option=com_ticketstation&view=paymentresult&ordercode=" . $orderCode;
$resultUrl = Route::_('index.php?option=com_ticketstation&view=paymentresult&ordercode=' . $orderCode . ($itemid ? '&Itemid=' . $itemid : ''), false);

?>

<div class="ticketstation ticketstation--paymentresult ticketstation--paymentresult-wait">
    <div class="ts-wait" role="status" aria-live="polite">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_CHECKING'); ?></h1>
        <p><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_PLEASE_WAIT'); ?></p>
        <div class="ts-spinner" aria-hidden="true"></div>
    </div>
</div>

<script>
    (function() {
        const orderCode = "<?php echo $orderCode; ?>";
        const pollUrl = "<?php echo $pollUrl; ?>";
        const resultUrl = "<?php echo $resultUrl; ?>";

        function pollStatus() {
            fetch(pollUrl + "&ordercode=" + orderCode)
                .then(res => res.json())
                .then(data => {
                    if (data.processed === true) {
                        window.location.href = resultUrl;
                    } else {
                        setTimeout(pollStatus, 1000);
                    }
                })
                .catch(() => setTimeout(pollStatus, 1500));
        }

        setTimeout(pollStatus, 1000);
    })();
</script>
