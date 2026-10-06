<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Wallet;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletApple;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletGoogle;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;


class ConfigurationController extends BaseController {

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Configuration';

    public function __construct($config = array(), ?MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        //$this->registerTask('main');
    }

    public function main($cachable = false, $urlparams = array()) {


        return parent::display($cachable, $urlparams);
    }

    /**
     * Handle the apply task which saves the configuration settings and shows the page again
     */
    public function apply($cachable = false, $urlparams = []) {


        $app 	= Factory::getApplication();
        $jinput = $app->getInput();
        $post 	= $jinput->post->getArray();

        $post['valuta'] = $app->getInput()->get('valuta', null, 'raw');

        // Notation of prices: a choice of decimals, decimal point and thousands separator
        if (isset($post['price_decimals']))
        {
            $post['price_decimals']      = in_array((int) $post['price_decimals'], [-1, 0, 2], true) ? (int) $post['price_decimals'] : 2;
            $post['price_decimal_sep']   = ($post['price_decimal_sep'] ?? ',') === '.' ? '.' : ',';
            $thousands                   = (string) $app->getInput()->get('price_thousands_sep', '', 'raw');
            $post['price_thousands_sep'] = $thousands === 'nbsp' ? "\u{00A0}" : (in_array($thousands, ['.', ',', "'"], true) ? $thousands : '');
            $post['price_symbol_after']  = empty($post['price_symbol_after']) ? 0 : 1;

            if ($post['price_thousands_sep'] === $post['price_decimal_sep'])
            {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Configuration', Text::_('COM_TICKETSTATION_PRICE_SEPARATORS_EQUAL'), 'error');

                return false;
            }
        }

        // Date and time notations are PHP date formats typed in by hand
        foreach (['dateformat' => 'd-m-Y', 'time_format' => 'H:i'] as $name => $default)
        {
            if (isset($post[$name]))
            {
                $post[$name] = mb_substr(trim((string) $app->getInput()->get($name, '', 'raw')), 0, 32) ?: $default;
            }
        }
        // Next order number: empty (default) or a whole number of at most 6 digits. Longer
        // numbers would run into the legacy 7-digit and temporary 9-digit ordercodes.
        if (isset($post['next_ordercode']))
        {
            $post['next_ordercode'] = trim((string) $post['next_ordercode']);

            if ($post['next_ordercode'] !== ''
                && ! preg_match('/^[1-9][0-9]{0,' . (Ordercode::SEQUENTIAL_MAX_DIGITS - 1) . '}$/', $post['next_ordercode']))
            {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Configuration', Text::_('COM_TICKETSTATION_NEXT_ORDERCODE_INVALID'), 'error');

                return false;
            }
        }

        // Wallet: the certificate, keys and service account only come from the uploads
        try
        {
            $post = $this->walletSettings($post);
        }
        catch (\RuntimeException $e)
        {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Configuration', Text::_($e->getMessage()), 'error');

            return false;
        }

        $model = $this->getModel('Configuration', 'Administrator');

        if ($model->store($post))
        {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Configuration', Text::_('COM_TICKETSTATION_CONFIG_SAVED'));

            // A wallet that is switched on but can't make passes yet isn't offered to customers
            Wallet::reset();

            if ((int) ($post['wallet_apple'] ?? 0) === 1 && ! Wallet::appleReady())
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_WALLET_APPLE_NOT_READY'), 'warning');
            }

            if ((int) ($post['wallet_google'] ?? 0) === 1 && ! Wallet::googleReady())
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_WALLET_GOOGLE_NOT_READY'), 'warning');
            }

            return true;
        }

        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Configuration', Text::_('COM_TICKETSTATION_CONFIG_NOTSAVED'), 'error');

        return false;
    }
    /**
     * The wallet part of the posted settings: the colours checked, and the Apple certificate
     * and Google service account taken from the uploaded files. Those are never posted as
     * plain fields, and an empty upload keeps what is stored.
     *
     * @param   array  $post
     *
     * @return  array
     *
     * @throws  \RuntimeException  With a language key, when an upload can't be used
     */
    private function walletSettings(array $post): array
    {
        $files  = $this->app->getInput()->files;
        $config = Wallet::config();

        $p12Password = (string) ($post['wallet_apple_p12_password'] ?? '');

        unset($post['wallet_apple_cert'], $post['wallet_apple_key'], $post['wallet_apple_pending_key'],
            $post['wallet_google_key'], $post['wallet_apple_p12_password']);

        $post['wallet_bg_color'] = Wallet::color($post['wallet_bg_color'] ?? '', Wallet::DEFAULT_BACKGROUND);
        $post['wallet_fg_color'] = Wallet::color($post['wallet_fg_color'] ?? '', Wallet::DEFAULT_FOREGROUND);

        if (isset($post['wallet_google_issuer_id']))
        {
            $post['wallet_google_issuer_id'] = trim((string) $post['wallet_google_issuer_id']);

            if ($post['wallet_google_issuer_id'] !== '' && ! WalletGoogle::validIssuerId($post['wallet_google_issuer_id']))
            {
                throw new \RuntimeException('COM_TICKETSTATION_WALLET_GOOGLE_ISSUER_INVALID');
            }
        }

        // Apple: the certificate downloaded from Apple, which belongs to the key of the
        // certificate request made here (or, when renewing, the key in use)
        $cer = $this->upload($files->get('wallet_apple_cer', [], 'raw'));

        if ($cer !== null)
        {
            [$post['wallet_apple_cert'], $post['wallet_apple_key']] = WalletApple::importCertificate($cer, [
                (string) $config->wallet_apple_pending_key,
                (string) $config->wallet_apple_key,
            ]);

            if ($post['wallet_apple_key'] === (string) $config->wallet_apple_pending_key)
            {
                $post['wallet_apple_pending_key'] = '';
            }
        }

        // Apple: or a .p12 exported on a Mac, with its password
        $p12 = $this->upload($files->get('wallet_apple_p12', [], 'raw'));

        if ($p12 !== null)
        {
            [$post['wallet_apple_cert'], $post['wallet_apple_key']] = WalletApple::importP12($p12, $p12Password);
        }

        // Google: the JSON key file of the service account
        $json = $this->upload($files->get('wallet_google_json', [], 'raw'));

        if ($json !== null)
        {
            if (WalletGoogle::serviceAccount($json) === null)
            {
                throw new \RuntimeException('COM_TICKETSTATION_WALLET_GOOGLE_KEY_INVALID');
            }

            $post['wallet_google_key'] = $json;
        }

        // Removing the stored credentials also switches the wallet off
        if ( ! empty($post['wallet_apple_remove']))
        {
            $post['wallet_apple']             = 0;
            $post['wallet_apple_cert']        = '';
            $post['wallet_apple_key']         = '';
            $post['wallet_apple_pending_key'] = '';
        }

        if ( ! empty($post['wallet_google_remove']))
        {
            $post['wallet_google']     = 0;
            $post['wallet_google_key'] = '';
        }

        unset($post['wallet_apple_remove'], $post['wallet_google_remove']);

        return $post;
    }

    /**
     * The content of an uploaded file, or null when none was chosen.
     *
     * @throws  \RuntimeException  When the upload failed
     */
    private function upload($file): ?string
    {
        if ( ! is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)
        {
            return null;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file($file['tmp_name']) || $file['size'] > 1024 * 1024)
        {
            throw new \RuntimeException('COM_TICKETSTATION_WALLET_UPLOAD_FAILED');
        }

        return (string) file_get_contents($file['tmp_name']);
    }

    /**
     * Apple Wallet: makes a private key and downloads the certificate signing request for it,
     * to upload on the Apple Developer website. The key waits in the Configuration until the
     * certificate Apple makes from the request is uploaded; the certificate in use (if any)
     * keeps working meanwhile.
     */
    public function walletcsr()
    {
        $app    = $this->app;
        $config = (new Config)->get(['companyname']);
        $name   = trim((string) ($config->companyname ?? '')) ?: (string) $app->get('sitename');

        try
        {
            [$csr, $key] = WalletApple::createSigningRequest($name, (new Config)->getContactEmail());
        }
        catch (\RuntimeException $e)
        {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Configuration', Text::_($e->getMessage()), 'error');

            return false;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_config'))
            ->set($db->quoteName('wallet_apple_pending_key') . ' = ' . $db->quote($key))
            ->where($db->quoteName('configid') . ' = 1');

        $db->setQuery($query)->execute();

        header('Content-Type: application/pkcs10');
        header('Content-Disposition: attachment; filename="ticketstation.certSigningRequest"');
        header('Content-Length: ' . strlen($csr));
        header('Cache-Control: private, no-store');
        echo $csr;

        $app->close();
    }

    /**
     * Handle the save task which saves the configuration settings and returns to the Control Panel page
     */
    public function save($cachable = false, $urlparams = []) {
        // On a failure, apply() keeps the settings page open with the error.
        if ($this->apply()) {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation', Text::_('COM_TICKETSTATION_CONFIG_SAVED'));
        }
    }
    /**
     * Handle the cancel task which doesn't save anything and returns to the Control Panel page
     */
    public function cancel($cachable = false, $urlparams = []) {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation');
    }
}