function getTimeRemaining(endtime) {
    //const total = Date.parse(endtime) - Date.parse(new Date());
    const total = endtime - parseDate(new Date());
    const seconds = Math.floor((total / 1000) % 60);
    const minutes = Math.floor((total / 1000 / 60) % 60);
    const hours = Math.floor((total / (1000 * 60 * 60)) % 24);
    const days = Math.floor(total / (1000 * 60 * 60 * 24));

    return {
        total,
        days,
        hours,
        minutes,
        seconds
    };
}

function initializeClock(id, deadline) {
    //const endtime = new Date(Date.parse(deadline));
    //const endtime = new Date(deadline);
    const endtime = parseDate(deadline);

    /*
    const now = new Date();
    SimpleDateFormat format = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.sssXXX");
    String endtime = format.format(deadline);
    String strnow = format.format(now)
    */

    const daysSpan = document.getElementById('days'+id);
    const hoursSpan = document.getElementById('hours'+id);
    const minutesSpan = document.getElementById('minutes'+id);
    const secondsSpan = document.getElementById('seconds'+id);
    const renewpageSpan = document.getElementById('renewpage'+id);
    const daysCaption = document.getElementById('dayscaption'+id);
    const hoursCaption = document.getElementById('hourscaption'+id);
    const minutesCaption = document.getElementById('minutescaption'+id);
    const secondsCaption = document.getElementById('secondscaption'+id);

    function updateClock() {
        const t = getTimeRemaining(endtime);

        daysSpan.innerText = t.days;
        //hoursSpan.innerText = ('0' + t.hours).slice(-2);
        //minutesSpan.innerText = ('0' + t.minutes).slice(-2);
        //secondsSpan.innerText = ('0' + t.seconds).slice(-2);
        hoursSpan.innerText = t.hours;
        minutesSpan.innerText = t.minutes;
        secondsSpan.innerText = t.seconds;

        if (t.days == 1) {
            daysCaption.innerText = countdownText('COM_TICKETSTATION_COUNTDOWN_DAY', 'day');
        } else {
            daysCaption.innerText = countdownText('COM_TICKETSTATION_COUNTDOWN_DAYS', 'days');
        }
        if (t.hours == 1) {
            hoursCaption.innerText = countdownText('COM_TICKETSTATION_COUNTDOWN_HOUR', 'hour');
        } else {
            hoursCaption.innerText = countdownText('COM_TICKETSTATION_COUNTDOWN_HOURS', 'hours');
        }
        if (t.minutes == 1) {
            minutesCaption.innerText = countdownText('COM_TICKETSTATION_COUNTDOWN_MINUTE', 'minute');
        } else {
            minutesCaption.innerText = countdownText('COM_TICKETSTATION_COUNTDOWN_MINUTES', 'minutes');
        }
        if (t.seconds == 1) {
            secondsCaption.innerText = countdownText('COM_TICKETSTATION_COUNTDOWN_SECOND', 'second');
        } else {
            secondsCaption.innerText = countdownText('COM_TICKETSTATION_COUNTDOWN_SECONDS', 'seconds');
        }

        if (t.days <= 0) {
            if (!!document.getElementById('daysdiv'+id)) {
                document.getElementById('daysdiv'+id).remove();
            }
            if (t.hours <= 0) {
                if (!!document.getElementById('hoursdiv'+id)) {
                    document.getElementById('hoursdiv'+id).remove();
                }
                if (t.minutes <= 0) {
                    if (!!document.getElementById('minutesdiv'+id)) {
                        document.getElementById('minutesdiv'+id).remove();
                    }
                    if (t.seconds <= 0) {
                        if (!!document.getElementById('secondsdiv'+id)) {
                            document.getElementById('secondsdiv'+id).remove();
                            document.getElementById('startverkooptiteldiv'+id).remove();
                            document.getElementById('startverkoopdatumtijddiv'+id).remove();
                            renewpageSpan.innerText = countdownText('COM_TICKETSTATION_COUNTDOWN_RELOADING', 'ONE MOMENT... THIS PAGE IS BEING REFRESHED');
                            setTimeout(
                                function() {
                                    window.location.reload();
                                },
                                3000);
                        }
                    }
                }
            }
        }

        if (t.total <= 0) {
            clearInterval(timeinterval);
        }
    }

    updateClock();
    const timeinterval = setInterval(updateClock, 1000);
}

// Language strings are handed over by the upcoming view through Text::script();
// the fallback only shows when Joomla's core.js is missing.
function countdownText(key, fallback) {
    if (window.Joomla && Joomla.Text) {
        return Joomla.Text._(key, fallback);
    }

    return fallback;
}

function parseDate(date) {
    const parsed = Date.parse(date);
    if (!isNaN(parsed)) {
        return parsed;
    }

    return Date.parse(date.replace(/-/g, '/').replace(/[a-z]+/gi, ' '));
}