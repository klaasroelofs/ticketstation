<?php

namespace setasign\Fpdi;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeEnlarge;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeNone;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Joomla\Filesystem\Folder;

defined('_JEXEC') or die('Restricted access');

require_once __DIR__ . '/autoload.php';
require_once (JPATH_COMPONENT . '/autoloader.php');

class FPDI_EAN13 extends Fpdi
{
    ## $mt is used by the multi ticket.
    function EAN13($x, $y, $barcode, $pdf_use_qrcode, $qr_width=0, $qr_background='fff', $h=10, $w=.35, $mt=0)
    {

        $barcode = $this->Barcode($x, $y, $barcode, $pdf_use_qrcode, $qr_width, $qr_background, $h, $w, 13, $mt);

        return $barcode;

    }

    function GetCheckDigit($barcode)
    {
        //Compute the check digit
        $sum=0;
        for($i=1;$i<=11;$i+=2)
        $sum+=3*$barcode[$i];
        for($i=0;$i<=10;$i+=2)
        $sum+=$barcode[$i];
        $r=$sum%10;
        if($r>0)
        $r=10-$r;
        return $r;
    }

    function TestCheckDigit($barcode)
    {
        //Test validity of check digit
        $sum=0;
        for($i=1;$i<=11;$i+=2)
        $sum+=3*$barcode[$i];
        for($i=0;$i<=10;$i+=2)
        $sum+=$barcode[$i];
        return ($sum+$barcode[12])%10==0;
    }

    function Barcode($x, $y, $barcode, $pdf_use_qrcode, $qr_width, $qr_background, $h, $w, $len, $mt)
    {


        $new_barcode = substr($barcode,-$len);
        ## KLAASR: md5 hash the barcode for better security
        $new_barcode = md5($new_barcode);

        if ($mt == 1){
            return true;
        }

        ## Creating the QR Code for printing.
        if ($pdf_use_qrcode == true) {

            //$this->get_qr_image($qr_width,$qr_background,$new_barcode);

            $this->get_qr_image_with_logo($new_barcode, $qr_width);

        }

        return $new_barcode;

    }

    function get_qr_image($qr_width,$qr_background,$new_barcode,$localfile = '')
    {
        $remoteFile ='https://api.qrserver.com/v1/create-qr-code/?size='.$qr_width.'x'.$qr_width.'&bgcolor='.$qr_background.'&data='.$new_barcode;
        if ($localfile == '') {
            $localfile = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/cache/' . $new_barcode . '.png';
        }

        ## Using the cache folder to save the file.
        $cache_folder = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/cache';

        ## If the folder doesn't exsist.
        if ( !is_dir($cache_folder) )
        {
            ## Making the folder right now.
            ## Now move the file away for security reasons
            Folder::create($cache_folder, 0755);
        }

        $ch = curl_init();
        $timeout = 0;
        curl_setopt ($ch, CURLOPT_URL, $remoteFile);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        $image = curl_exec($ch);
        curl_close($ch);
        $f = fopen($localfile, 'w');
        fwrite($f, $image);
        fclose($f);
    }

    function get_qr_image_with_logo($barcode, $qr_width, $destinationpath='', $filetype = 'PNG')
    {

        if ($destinationpath == '') {
            $destinationpath = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/cache/' . $barcode . '.png';
        }

        if ($filetype == 'SVG') {
            $writer = new SvgWriter();
        } else {
            $writer = new PngWriter();
        }

        // Create QR code
        $qrCode = QrCode::create($barcode)
            ->setEncoding(new Encoding('UTF-8'))
            //->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
            //->setErrorCorrectionLevel(new ErrorCorrectionLevelQuartile())
            //->setErrorCorrectionLevel(new ErrorCorrectionLevelMedium())
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
            ->setSize($qr_width)
            ->setMargin(0)
            //->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->setRoundBlockSizeMode(new RoundBlockSizeModeEnlarge())
            //->setRoundBlockSizeMode(new RoundBlockSizeModeNone())
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));

        $result = $writer->write($qrCode);

        // Save it to a file
        $result->saveToFile($destinationpath);

    }




}
