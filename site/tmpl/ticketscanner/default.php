<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$app        = Factory::getApplication();
$wa         = Factory::getApplication()->getDocument()->getWebAssetManager();
$document   = $app->getDocument();
$document->setTitle( 'Ticket Scanner' . ' - ' . $app->get('sitename'));

$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
HTMLHelper::_('jquery.framework');
$document->addScript('components/com_ticketstation/assets/javascripts/qrLibrary.js');
$wa->registerAndUseStyle('fontawesome-free.all', 'media/vendor/fontawesome-free/css/all.min.css', [], [], []);

if (!empty($this->event)) {
    $event = $this->event->eventname;
    $variable_to_validate = strval('eventid=' . $this->event->eventid);
} else {
    $event = $this->ticket->eventname;
    $ticket = $this->ticket->ticketname;
    $variable_to_validate = strval('ticketid=' . $this->ticket->ticketid);
}

$itemid = TicketstationFunctions::getSiteItemid();
$linkback = Route::_('index.php?option=com_ticketstation&view=ticketscanning' . ($itemid ? '&Itemid=' . $itemid : ''));

?>

<div class="ticketstation">

    <div id="container">
        <div class="btn-scan-background">
            <div id="btn-scan-qr">
                START
            </div>
        </div>

        <div id="scanpage-title-underlay" class="scanpage-title-underlay">
			<span>
				<?php if (!empty($ticket)) { ?>
                    <h3 style="margin:5px 0px;"><strong><?php echo $event; ?></strong></h3>
                    <h4 style="margin-top:5px;"><strong><?php echo $ticket; ?></strong></h4>
                <?php } else { ?>
                    <h3 style="margin-top:18px;"><strong><?php echo $event; ?></strong></h3>
                <?php } ?>
			</span>
            <span class="scanpage-copyright">
				© Klaas Roelofs
			</span>
            <span hidden id="fullscreen">
				<a id="btn-fullscreen" class="btn btn-fullscreen">
					<span id="fullscreen-icon" class="fas fa-expand"></span>
				</a>
			</span>
        </div>

        <div id="video-container">
            <div hidden id="scan-region-highlight">
                <svg class="scan-region-highlight-svg" viewBox="0 0 238 238" preserveAspectRatio="none" style="position:absolute;width:100%;height:100%;left:0;top:0;fill:none;stroke:#fff;stroke-width:5;stroke-linecap:round;stroke-linejoin:round;"><path d="M31 2H10a8 8 0 0 0-8 8v21M207 2h21a8 8 0 0 1 8 8v21m0 176v21a8 8 0 0 1-8 8h-21m-176 0H10a8 8 0 0 1-8-8v-21"></path></svg>
            </div>
            <canvas hidden id="qr-canvas"></canvas>
        </div>

        <div hidden id="scanresultcontainer" class="scanresultcontainer">
            <div>
                <span id="scanresultorder"></span>
            </div>
            <div>
                <span id="scanresulttext"></span>
            </div>
        </div>

        <div hidden id="manualentrycontainer">
            <h3 style="text-align:left;text-transform:uppercase;"><strong>Handmatige controle</strong></h3>
            <form class="manualentryform">
                <label for="manualentryInput" style="font-size:initial;">Ticket ID (5 cijfers): </label>
                <input type="number" autocomplete="off" name="manualentryInput" id="manualentryInput">

                <button class="btn btn-primary btn-manualsubmit" style="margin-top:20px;">
                    Controleer
                </button>
            </form>
        </div>

        <div style="display: none;">
            <div hidden id="getscoinscontainer">
                <div id="getscoinstitle" class="scanpage-textshadow">
                    MUNTEN
                </div>

                <div id="getscoinsbox" class="scanpage-boxshadow">
                    <span id="getscoinstext"></span>
                </div>
            </div>
        </div>

        <div hidden id="scanhistorycontainer">
            <h4 id="scanhistorytitle"><strong>Resultaat vorige scan:</strong></h4>
            <div id="scanhistory" class="scanhistory">
                <div>
                    <span id="scanhistoryorder"></span>
                </div>
                <div>
                    <span id="scanhistorytext"></span>
                </div>
            </div>
        </div>

        <div id="control-bar" class="control-bar">
			<span id="backbutton" >
				<a class="btn btn-primary btn-back" onclick="document.location.href='<?php echo $linkback; ?>'">
					<span>TERUG</span>
				</a>
			</span>
            <span hidden id="stopscanning">
				<a class="btn btn-primary btn-stopscanning">
					<span>STOP</span>
				</a>
			</span>
            <span id="controls">
				<span hidden id="manualentry">
					<a id="btn-manualentry" class="btn btn-manualentry">
						<span id="manualentry-icon" class="bi bi-pen"></span>
					</a>
				</span>
				<span hidden id="torch">
					<a id="btn-torch" class="btn btn-torch">
						<span id="torch-icon" class="bi bi-lightbulb-off"></span>
					</a>
				</span>
				<span id="sound">
					<a id="btn-sound" class="btn btn-sound sound-on">
						<span id="sound-icon" class="fas fa-volume-up"></span>
					</a>
				</span>
				<span hidden id="vibrate">
					<a id="btn-vibrate" class="btn btn-vibrate vibrate-on">
						<span id="vibrate-icon" class="bi bi-phone-vibrate  "></span>
					</a>
				</span>
			</span>
            <span hidden id="scanned-tickets">
				Totaal gescand: <span id="total-scanned"></span>/<?php echo $this->sold;?>
			</span>
            <span hidden id="scanned-tickets-session">
				Gescand door jou: <span id="total-scanned-session"></span>
			</span>
        </div>



    </div>

</div>

<script language="javascript">

    const qrcode2 = window.qrcode;

    const video = document.createElement("video");
    const canvasElement = document.getElementById("qr-canvas");
    const canvas = canvasElement.getContext("2d");
    const scanhighlight = document.getElementById("scan-region-highlight");

    const scanresultcontainer = document.getElementById("scanresultcontainer");
    const scanresultorder = document.getElementById("scanresultorder");
    const scanresulttext = document.getElementById("scanresulttext");

    const btnBack = document.getElementById("backbutton");
    const btnStopscanning = document.getElementById("stopscanning");

    const ScannedTickets = document.getElementById("scanned-tickets");
    const TotalScanned = document.getElementById("total-scanned");
    const ScannedTicketsSession = document.getElementById("scanned-tickets-session");
    const TotalScannedSession = document.getElementById("total-scanned-session");

    const btnFullscreen = document.getElementById("fullscreen");
    const btnFullscreenInner = document.getElementById("btn-fullscreen");
    const btnFullscreenIcon = document.getElementById("fullscreen-icon");

    const manualentrycontainer = document.getElementById("manualentrycontainer");

    const controlbar = document.getElementById("control-bar");
    const btnManualEntry = document.getElementById("manualentry");
    const btnManualEntryInner = document.getElementById("btn-manualentry");

    const btnTorch = document.getElementById("torch");
    const btnTorchInner = document.getElementById("btn-torch");
    const btnTorchIcon = document.getElementById("torch-icon");

    const btnSound = document.getElementById("sound");
    const btnSoundInner = document.getElementById("btn-sound");
    const btnSoundIcon = document.getElementById("sound-icon");

    const btnVibrate = document.getElementById("vibrate");
    const btnVibrateInner = document.getElementById("btn-vibrate");
    const btnVibrateIcon = document.getElementById("vibrate-icon");

    const btnScanQR = document.getElementById("btn-scan-qr");

    const getscoinstitle = document.getElementById("getscoinstitle");
    const getscoinsbox = document.getElementById("getscoinsbox");
    const getscoinstext = document.getElementById("getscoinstext");

    const scanhistoryTitle = document.getElementById("scanhistorytitle");
    const scanhistory = document.getElementById("scanhistory");
    const scanhistoryorder = document.getElementById("scanhistoryorder");
    const scanhistorytext = document.getElementById("scanhistorytext");

    const totals_visible = '<?php echo $this->scanner_permissions->totals_visible; ?>';
    const manual_entry = '<?php echo $this->scanner_permissions->manual_entry; ?>';
    const soundEffect = new Audio();

    let canvibrate = false;

    let scanning = false;
    let manualentry = false;
    let torch = false;
    let sound = true;
    let vibration = true;

    let scancount = 0;
    let CheckResult = '';
    let text = '';
    let order = '';
    let totalscanned = '';
    let getscoins = '';

    //Handmatige ticketcontrole
    let form = document.querySelector(".manualentryform");

    form.addEventListener("submit", function (e) {
        e.preventDefault() // This prevents the window from reloading

        let formdata = new FormData(this);
        let ManualInput = formdata.get("manualentryInput");

        document.getElementById("manualentryInput").value = "";

        toggleManualentry();
        btnBack.hidden = true;
        btnScanQR.hidden = true;
        btnStopscanning.hidden = false;

        scancount = scancount + 1;
        scanResult(ManualInput);

    });

    //Knop voor fullscreen, lamp en vibratie alleen beschikbaar op Android devices
    if (getMobileOperatingSystem() == 'Android') {
        //btnFullscreen.hidden = false;
        btnTorch.hidden = false;
        canvibrate = true;
        btnVibrate.hidden = false;
    }

    jQuery("#scanpage-title-underlay").show();
    jQuery("#scanpage-title-underlay").animate({ top: "0px" }, 750);
    jQuery("#control-bar").slideDown(750);

    if (manual_entry == '1') {
        btnManualEntry.hidden = false;
    }

    scanresultcontainer.onclick = () => {
        startscanning();
    };

    btnScanQR.onclick = () => {
        startscanning();

        //Stil MP3 bestand afspelen. Op iOS kan eerste keer afspelen van geluid namelijk alleen bij gebruikersinteractie.
        soundEffect.src ="data:audio/mpeg;base64,SUQzBAAAAAABEVRYWFgAAAAtAAADY29tbWVudABCaWdTb3VuZEJhbmsuY29tIC8gTGFTb25vdGhlcXVlLm9yZwBURU5DAAAAHQAAA1N3aXRjaCBQbHVzIMKpIE5DSCBTb2Z0d2FyZQBUSVQyAAAABgAAAzIyMzUAVFNTRQAAAA8AAANMYXZmNTcuODMuMTAwAAAAAAAAAAAAAAD/80DEAAAAA0gAAAAATEFNRTMuMTAwVVVVVVVVVVVVVUxBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVf/zQsRbAAADSAAAAABVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVf/zQMSkAAADSAAAAABVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV";
        soundEffect.loop = false;
        soundEffect.play();
    };

    btnStopscanning.onclick = () => {
        scanning = false;

        btnScanQR.hidden = false;
        btnBack.hidden = false;
        if (manual_entry == '1') {
            btnManualEntry.hidden = false;
        }
        btnStopscanning.hidden = true;

        manualentrycontainer.hidden = true;
        scanresultcontainer.hidden = true;
        jQuery("#getscoinscontainer").hide();
        jQuery("#scanhistorycontainer").hide();

        canvasElement.hidden = true;
        scanhighlight.hidden = true;

        totalscanned = TotalScanned.innerText;

        if (torch === true) {
            toggleTorch();
        }
        video.srcObject.getTracks().forEach(track => {
            track.stop();
        });

    };

    btnFullscreen.onclick = () => {
        toggleFullScreen();
    };

    btnBack.onclick = () => {
        location.href='<?php echo $linkback; ?>';
    };

    btnManualEntry.onclick = () => {
        toggleManualentry();
    };

    btnTorch.onclick = () => {
        toggleTorch();
    };

    btnSound.onclick = () => {
        if (sound === false) {
            sound = true;
            btnSoundIcon.classList.remove('fa-volume-mute');
            btnSoundIcon.classList.add('fa-volume-up');
            btnSoundInner.classList.add('sound-on');
        } else {
            sound = false;
            btnSoundIcon.classList.remove('fa-volume-up');
            btnSoundIcon.classList.add('fa-volume-mute');
            btnSoundInner.classList.remove('sound-on');
        }
    };

    btnVibrate.onclick = () => {
        if (vibration === false) {
            vibration = true;
            btnVibrateIcon.classList.remove('bi-phone');
            btnVibrateIcon.classList.add('bi-phone-vibrate');
            btnVibrateInner.classList.add('vibrate-on');
        } else {
            vibration = false;
            btnVibrateIcon.classList.remove('bi-phone-vibrate');
            btnVibrateIcon.classList.add('bi-phone');
            btnVibrateInner.classList.remove('vibrate-on');
        }
    };

    function getMobileOperatingSystem() {
        var userAgent = navigator.userAgent || navigator.vendor || window.opera;

        if (/android/i.test(userAgent)) {
            return "Android";
        }
        return "No Android";
    }

    qrcode2.callback = res => {
        if (res) {
            scanning = false;
            video.srcObject.getTracks().forEach(track => {
                track.stop();
            });
            scancount = scancount + 1;
            scanResult(res);

            scanhistoryTitle.classList.remove("scanpage-textshadow");
            scanhistory.classList.remove("scanpage-boxshadow");

        }
    };

    function startscanning() {
        navigator.mediaDevices
            .getUserMedia({ video: { facingMode: "environment" } })
            .then(function(stream) {
                scanning = true;

                btnScanQR.hidden = true;
                btnBack.hidden = true;
                btnManualEntry.hidden = true;
                btnStopscanning.hidden = false;

                scanresultcontainer.hidden = true;

                canvasElement.hidden = false;
                scanhighlight.hidden = false;

                scanhistoryTitle.classList.add("scanpage-textshadow");
                scanhistory.classList.add("scanpage-boxshadow");

                if (order !== '') {
                    scanhistoryorder.innerText = text;
                    scanhistorytext.innerText = order;
                    jQuery("#scanhistorycontainer").delay(300).fadeIn(500);

                    if (CheckResult == '0') {
                        scanhistory.classList.add('negative');
                        scanhistory.classList.remove('positive');
                    } else {
                        scanhistory.classList.add('positive');
                        scanhistory.classList.remove('negative');
                    }

                    jQuery("#getscoinscontainer").delay(300).fadeIn(500);

                }

                if ((totalscanned != '0') && (totalscanned !== '') && (totals_visible == '1')) {
                    ScannedTickets.hidden = false;
                    ScannedTicketsSession.hidden = false;
                }

                video.setAttribute("playsinline", true); // required to tell iOS safari we don't want fullscreen
                video.srcObject = stream;
                video.play();

                if (torch === true) {
                    video.srcObject.getVideoTracks()[0].applyConstraints({
                        advanced: [{torch: true}]
                    });
                }
                tick();
                scan();
            });
    }

    function tick() {
        canvasElement.height = video.videoHeight;
        canvasElement.width = video.videoWidth;
        canvas.drawImage(video, 0, 0, canvasElement.width, canvasElement.height);

        scanning = true && requestAnimationFrame(tick);
    }

    function scan() {
        try {
            qrcode2.decode();
        } catch (e) {
            setTimeout(scan, 400);
        }
    }

    function PlaySound(sts_snd) {
        if (sound === true) {
            if (sts_snd === '0') {
                soundEffect.src = "/components/com_ticketstation/assets/sounds/error.mp3";
                soundEffect.play();
            } else {
                soundEffect.src = "/components/com_ticketstation/assets/sounds/success.mp3";
                soundEffect.play();
            }

        }
    }

    function Vibrate(sts_vbr) {
        if (vibration === true) {
            if (sts_vbr == '0') {
                navigator.vibrate([250, 100, 250, 100, 250]);
            } else {
                navigator.vibrate(150);
            }
        }
    }

    function toggleTorch() {
        if (torch === false) {
            torch = true;
            btnTorchIcon.classList.remove('bi-lightbulb-off');
            btnTorchIcon.classList.add('bi-lightbulb');
            btnTorchInner.classList.add('torch-on');

            video.srcObject.getVideoTracks()[0].applyConstraints({
                advanced: [{torch: true}]
            });
        } else {
            torch = false;
            btnTorchIcon.classList.remove('bi-lightbulb');
            btnTorchIcon.classList.add('bi-lightbulb-off');
            btnTorchInner.classList.remove('torch-on');
            video.srcObject.getVideoTracks()[0].applyConstraints({
                advanced: [{torch: false}]
            });
        }
    }

    function toggleManualentry() {
        if (manualentry	=== false) {
            manualentry = true;
            btnScanQR.hidden = true;
            btnBack.hidden = true;
            btnStopscanning.hidden = true;
            scanresultcontainer.hidden = true;
            btnManualEntryInner.classList.add('manualentry-on');
            manualentrycontainer.hidden = false;
        } else {
            manualentry = false;
            btnScanQR.hidden = false;
            btnBack.hidden = false;
            btnManualEntryInner.classList.remove('manualentry-on');
            manualentrycontainer.hidden = true;
        }
    }

    function scanResult(barcode){

        var url = "/index.php?option=com_ticketstation&controller=codescanner&task=validation&tid="+barcode+"&<?php echo $variable_to_validate; ?>";
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.open("POST", url , true);
        xmlhttp.onreadystatechange = function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                var xmlDoc = xmlhttp.responseXML;
                CheckResult = xmlDoc.getElementsByTagName("status")[0].childNodes[0].nodeValue;
                text = xmlDoc.getElementsByTagName("text")[0].childNodes[0].nodeValue;
                order = xmlDoc.getElementsByTagName("order")[0].childNodes[0].nodeValue;
                totalscanned = xmlDoc.getElementsByTagName("totalscanned")[0].childNodes[0].nodeValue;
                getscoins = xmlDoc.getElementsByTagName("getscoins")[0].childNodes[0].nodeValue;

                canvasElement.hidden = true;
                scanhighlight.hidden = true;
                scanresultcontainer.hidden = false;
                TotalScannedSession.innerText = scancount;

                jQuery("#getscoinscontainer").show();
                getscoinstext.innerText = getscoins;
                if (getscoins == 'GEEN') {
                    getscoinsbox.classList.add('negative-coins');
                    getscoinsbox.classList.remove('positive-coins');
                } else {
                    getscoinsbox.classList.add('positive-coins');
                    getscoinsbox.classList.remove('negative-coins');
                }

                if (CheckResult === '0') {
                    PlaySound(CheckResult);
                    if (canvibrate) {
                        Vibrate(CheckResult);
                    }

                    scanresultcontainer.classList.add('negative');
                    scanresultcontainer.classList.remove('positive');

                    if (isNaN(order.charAt(0))) {
                        scanresultorder.innerText = text;
                        scanresulttext.innerText = "";
                    } else {
                        scanresultorder.innerText = text;
                        scanresulttext.innerText = order;
                    }

                } else {
                    PlaySound(CheckResult);
                    if (canvibrate) {
                        Vibrate(CheckResult);
                    }

                    scanresultcontainer.classList.add('positive');
                    scanresultcontainer.classList.remove('negative');
                    scanresultorder.innerText = text;
                    scanresulttext.innerText = order;

                    TotalScanned.innerText = totalscanned;

                    //Auto Next Scan als ticket goedgekeurd
                    setTimeout(
                        function() {
                            startscanning();
                        }, 1400);
                }
            }
        };
        xmlhttp.send();

    }

    function toggleFullScreen() {

        var doc = window.document;
        var docEl = doc.documentElement;

        var requestFullScreen =
            docEl.requestFullscreen ||
            docEl.mozRequestFullScreen ||
            docEl.webkitRequestFullScreen ||
            docEl.msRequestFullscreen;
        var cancelFullScreen =
            doc.exitFullscreen ||
            doc.mozCancelFullScreen ||
            doc.webkitExitFullscreen ||
            doc.msExitFullscreen;

        if (
            !doc.fullscreenElement &&
            !doc.mozFullScreenElement &&
            !doc.webkitFullscreenElement &&
            !doc.msFullscreenElement
        ) {
            requestFullScreen.call(docEl);
            btnFullscreenIcon.classList.remove('fa-expand');
            btnFullscreenIcon.classList.add('fa-compress');
            controlbar.style.padding = "5px 15px";
        } else {
            cancelFullScreen.call(doc);
            btnFullscreenIcon.classList.add('fa-expand');
            btnFullscreenIcon.classList.remove('fa compress');
            controlbar.style.padding = "5px 0px";
        }

    }

</script>