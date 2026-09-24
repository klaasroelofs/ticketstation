<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

$orderCode = $this->orderCode;
$itemid = TicketstationFunctions::getSiteItemid();
$pollUrl = Uri::root() . "index.php?option=com_ticketstation&task=paymentresult.poll&format=json";
//$resultUrl = Uri::root() . "index.php?option=com_ticketstation&view=paymentresult&ordercode=" . $orderCode;
$resultUrl = Route::_('index.php?option=com_ticketstation&view=paymentresult&ordercode=' . $orderCode . ($itemid ? '&Itemid=' . $itemid : ''), false);

?>

<div style="text-align:center; padding:40px;">
    <h2><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_CHECKING'); ?></h2>
    <p><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_PLEASE_WAIT'); ?></p>
    <div class="spinner" style="margin-top:20px;"></div>
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

<style>
    .spinner {
        margin: 0 auto;
        width: 40px;
        height: 40px;
        border: 5px solid #ccc;
        border-top-color: #333;
        border-radius: 50%;
        animation: spin .8s linear infinite;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>
