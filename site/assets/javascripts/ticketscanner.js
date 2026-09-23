/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * Browser ticket scanner (view=ticketscanner).
 *
 * The camera and QR decoding are handled by qr-scanner (nimiq, MIT): it uses the
 * native BarcodeDetector where available and otherwise decodes in a Web Worker,
 * only looks at the centre of the image, and pauses by itself while the page is
 * hidden. The camera stays on between scans; while a result is being checked or
 * shown, decoded codes are simply ignored.
 *
 * States: idle -> scanning -> busy -> result-ok (auto back to scanning)
 *                                  -> result-error (camera paused, tap to continue)
 *         idle <-> manual (typed-in ticket id)
 */
import QrScanner from './qr-scanner/qr-scanner.min.js';

const config = JSON.parse(document.getElementById('ticketscanner-config').textContent);

const REQUEST_TIMEOUT_MS = 10000;
const RESULT_OK_MS       = 1400;
// A code that stays in view is ignored until it has been out of view this long.
const SAME_CODE_QUIET_MS = 3000;

const $ = (id) => document.getElementById(id);

const el = {
    title:          $('scanpage-title-underlay'),
    video:          $('qr-video'),
    highlight:      $('scan-region-highlight'),
    btnScan:        $('btn-scan-qr'),
    back:           $('backbutton'),
    backLink:       document.querySelector('#backbutton a'),
    stop:           $('stopscanning'),
    result:         $('scanresultcontainer'),
    resultText:     $('scanresultorder'),
    resultOrder:    $('scanresulttext'),
    history:        $('scanhistorycontainer'),
    historyBox:     $('scanhistory'),
    historyText:    $('scanhistoryorder'),
    historyOrder:   $('scanhistorytext'),
    manual:         $('manualentry'),
    manualBtn:      $('btn-manualentry'),
    manualBox:      $('manualentrycontainer'),
    manualForm:     document.querySelector('.manualentryform'),
    manualInput:    $('manualentryInput'),
    torch:          $('torch'),
    torchBtn:       $('btn-torch'),
    torchIcon:      $('torch-icon'),
    soundBtn:       $('btn-sound'),
    soundIcon:      $('sound-icon'),
    vibrate:        $('vibrate'),
    vibrateBtn:     $('btn-vibrate'),
    vibrateIcon:    $('vibrate-icon'),
    totals:         $('scanned-tickets'),
    totalScanned:   $('total-scanned'),
    totalsSession:  $('scanned-tickets-session'),
    totalSession:   $('total-scanned-session'),
};

const canVibrate = 'vibrate' in navigator;

let scanner     = null;
let state       = 'idle';
let sound       = true;
let vibration   = true;
let torchKnown  = false;
let approved    = 0;
let lastCode    = '';
let lastCodeAt  = 0;
let previous    = null;
let wakeLock    = null;
let resumeTimer = null;

/* ---------- sound & vibration ---------- */

const sounds = {
    ok:    new Audio(config.sounds.success),
    error: new Audio(config.sounds.error),
};
Object.values(sounds).forEach((audio) => { audio.preload = 'auto'; });

let audioUnlocked = false;

// iOS only plays audio that was first started from a user gesture.
function unlockAudio() {
    if (audioUnlocked) {
        return;
    }
    audioUnlocked = true;

    Object.values(sounds).forEach((audio) => {
        audio.muted = true;
        audio.play()
            .then(() => { audio.pause(); audio.currentTime = 0; })
            .catch(() => {})
            .finally(() => { audio.muted = false; });
    });
}

function signal(ok) {
    if (sound) {
        const audio = ok ? sounds.ok : sounds.error;
        audio.currentTime = 0;
        audio.play().catch(() => {});
    }

    if (canVibrate && vibration) {
        navigator.vibrate(ok ? 150 : [250, 100, 250, 100, 250]);
    }
}

/* ---------- screen wake lock ---------- */

async function keepScreenOn() {
    if (wakeLock || !('wakeLock' in navigator)) {
        return;
    }
    try {
        wakeLock = await navigator.wakeLock.request('screen');
        wakeLock.addEventListener('release', () => { wakeLock = null; });
    } catch (e) {
        wakeLock = null;
    }
}

function allowScreenOff() {
    if (wakeLock) {
        wakeLock.release().catch(() => {});
        wakeLock = null;
    }
}

/* ---------- UI ---------- */

function setSessionUi(active) {
    el.btnScan.hidden = active;
    el.back.hidden    = active;
    el.stop.hidden    = !active;
    el.manual.hidden  = active || !config.manualEntry;
    el.video.classList.toggle('is-active', active);
}

function showResult(ok, text, order) {
    el.result.classList.toggle('positive', ok);
    el.result.classList.toggle('negative', !ok);
    el.resultText.textContent  = text || '';
    el.resultOrder.textContent = order || '';
    el.result.hidden = false;
}

function hideResult() {
    el.result.hidden = true;
}

function showHistory() {
    if (!previous) {
        return;
    }
    el.historyText.textContent  = previous.text;
    el.historyOrder.textContent = previous.order;
    el.historyBox.classList.toggle('positive', previous.ok);
    el.historyBox.classList.toggle('negative', !previous.ok);
    el.history.hidden = false;
}

function showTotals() {
    if (config.totalsVisible && approved > 0) {
        el.totals.hidden        = false;
        el.totalsSession.hidden = false;
    }
}

function updateTorchIcon() {
    const on = !!(scanner && scanner.isFlashOn());
    el.torchIcon.classList.toggle('bi-lightbulb', on);
    el.torchIcon.classList.toggle('bi-lightbulb-off', !on);
    el.torchBtn.classList.toggle('torch-on', on);
}

/* ---------- scanning ---------- */

function getScanner() {
    if (!scanner) {
        scanner = new QrScanner(el.video, (result) => onCode(result.data), {
            returnDetailedScanResult: true,
            preferredCamera: 'environment',
            maxScansPerSecond: 10,
            highlightScanRegion: true,
            overlay: el.highlight,
        });
    }
    return scanner;
}

async function startScanning() {
    clearTimeout(resumeTimer);
    hideResult();
    el.manualBox.hidden = true;
    setSessionUi(true);
    state = 'scanning';

    // A code still in front of the camera must not be checked again right away.
    lastCodeAt = Date.now();

    try {
        await getScanner().start();
    } catch (e) {
        state = 'result-error';
        signal(false);
        showResult(false, config.texts.camera, '');
        return;
    }

    keepScreenOn();
    showHistory();
    showTotals();

    if (!torchKnown) {
        torchKnown = true;
        scanner.hasFlash().then((hasFlash) => { el.torch.hidden = !hasFlash; });
    }
}

function stopScanning() {
    clearTimeout(resumeTimer);
    state = 'idle';

    if (scanner) {
        scanner.stop();
    }
    allowScreenOff();

    hideResult();
    el.history.hidden = true;
    setSessionUi(false);
}

function onCode(code) {
    const now = Date.now();

    if (code === lastCode && now - lastCodeAt < SAME_CODE_QUIET_MS) {
        lastCodeAt = now;
        return;
    }

    if (state !== 'scanning') {
        return;
    }

    lastCode   = code;
    lastCodeAt = now;
    validate(code);
}

async function validate(code) {
    state = 'busy';

    const body = new FormData();
    body.append('tid', code);
    Object.entries(config.target).forEach(([key, value]) => body.append(key, value));
    body.append(config.token, '1');

    const controller = new AbortController();
    const timer      = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
    let result;

    try {
        const response = await fetch(config.scanUrl, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        const text = await response.text();

        try {
            result = JSON.parse(text);
        } catch (e) {
            // Not our JSON: an expired session / token is answered with plain text.
            result = { status: 0, text: response.ok ? config.texts.unauthorized : config.texts.network, order: '' };
        }
    } catch (e) {
        result = { status: 0, text: config.texts.network, order: '' };
        // Nothing was checked: the same ticket may be offered again immediately.
        lastCode = '';
    } finally {
        clearTimeout(timer);
    }

    if (state !== 'busy') {
        // Stopped while waiting for the answer.
        return;
    }

    handleResult(result);
}

function handleResult(result) {
    const ok = Number(result.status) === 1;

    signal(ok);
    showResult(ok, result.text, result.order);
    previous = { ok, text: result.text || '', order: result.order || '' };

    if (ok) {
        approved++;
        el.totalSession.textContent = approved;
        el.totalScanned.textContent = result.totalscanned;
        showTotals();

        state = 'result-ok';
        resumeTimer = setTimeout(startScanning, RESULT_OK_MS);
    } else {
        // Keep the red screen until it has been seen and tapped; the camera rests meanwhile.
        state = 'result-error';
        if (scanner) {
            scanner.pause();
        }
    }
}

/* ---------- manual entry ---------- */

function toggleManualEntry() {
    const open = el.manualBox.hidden;

    if (open) {
        stopScanning();
        state = 'manual';
    } else {
        state = 'idle';
    }

    el.manualBox.hidden = !open;
    el.btnScan.hidden   = open;
    el.back.hidden      = open;
    el.manualBtn.classList.toggle('manualentry-on', open);

    if (open) {
        el.manualInput.focus();
    }
}

el.manualForm.addEventListener('submit', (event) => {
    event.preventDefault();

    const value = el.manualInput.value.trim();
    el.manualInput.value = '';

    if (value === '') {
        return;
    }

    unlockAudio();
    el.manualBox.hidden = true;
    el.manualBtn.classList.remove('manualentry-on');
    setSessionUi(true);
    validate(value);
});

/* ---------- controls ---------- */

el.btnScan.addEventListener('click', () => {
    unlockAudio();
    startScanning();
});

el.result.addEventListener('click', () => {
    if (state === 'result-error' || state === 'result-ok') {
        startScanning();
    }
});

el.stop.addEventListener('click', stopScanning);

el.back.addEventListener('click', () => {
    window.location.href = el.backLink.href;
});

el.manual.addEventListener('click', toggleManualEntry);

el.torch.addEventListener('click', async () => {
    if (!scanner) {
        return;
    }
    try {
        await scanner.toggleFlash();
    } catch (e) {
        el.torch.hidden = true;
    }
    updateTorchIcon();
});

el.soundBtn.parentElement.addEventListener('click', () => {
    sound = !sound;
    el.soundIcon.classList.toggle('fa-volume-up', sound);
    el.soundIcon.classList.toggle('fa-volume-mute', !sound);
    el.soundBtn.classList.toggle('sound-on', sound);
});

el.vibrate.addEventListener('click', () => {
    vibration = !vibration;
    el.vibrateIcon.classList.toggle('bi-phone-vibrate', vibration);
    el.vibrateIcon.classList.toggle('bi-phone', !vibration);
    el.vibrateBtn.classList.toggle('vibrate-on', vibration);
});

// The wake lock is dropped whenever the page is hidden; take it again on return.
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && state !== 'idle' && state !== 'manual') {
        keepScreenOn();
    }
});

// Release the camera right away when leaving the page.
window.addEventListener('pagehide', () => {
    if (scanner) {
        scanner.destroy();
        scanner = null;
    }
    allowScreenOff();
});

// Coming back via the back/forward cache: the camera is gone, start over from idle.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        stopScanning();
    }
});

/* ---------- init ---------- */

el.vibrate.hidden = !canVibrate;
el.manual.hidden  = !config.manualEntry;
requestAnimationFrame(() => el.title.classList.add('is-visible'));
