function openWindow(url, target, width, height, flags) {
    var w = screen.width;
    var h = screen.height;

    var x = ( w / 2 ) - ( width / 2 );
    var y = ( h / 2 ) - ( height / 2 );

    window.open(url, target, 'width=' + width + ',height=' + height + ',' + flags);
}

function tabToggle(tabname) {
    if (curtab) curtab.style.display = 'none';
    curtab = document.getElementById(tabname);
    curtab.style.display = 'block';
}

function limitText(limitField, limitCount, limitNum) {
    if (limitField.value.length > limitNum) {
        limitField.value = limitField.value.substring(0, limitNum);
        limitCount.innerHTML = "0";
    }
    else {
        limitCount.innerHTML = limitNum - limitField.value.length;
    }
}

function createRequestObject() {
    var ro;
    var browser = navigator.appName;
    if (browser == "Microsoft Internet Explorer") {
        ro = new ActiveXObject("Microsoft.XMLHTTP");
    } else {
        ro = new XMLHttpRequest();
    }
    return ro;
}

var http = createRequestObject();

function sndReq(action) {
    http.open('get', action);
    http.onreadystatechange = handleResponse;
    http.send(null);
}

function handleResponse() {
    if (http.readyState == 4) {
        var response = http.responseText;
        var update = [];

        if (response.indexOf('|' != -1)) {
            update = response.split('|');
            document.getElementById(update[0]).innerHTML = update[1];
        }
    }
}

function ReverseContentDisplay(d) {
    if (d.length < 1) {
        return;
    }
    var dd = document.getElementById(d);
    if (dd.style.display != "block") {
        dd.style.display = "block";
    }
    else {
        dd.style.display = "none";
    }
}

function updateClock() {
    var currentTime = new Date();
    var currentHours = currentTime.getUTCHours();
    var currentMinutes = currentTime.getMinutes();

    currentHours = ( currentHours < 10 ? "0" : "" ) + currentHours;
    currentMinutes = ( currentMinutes < 10 ? "0" : "" ) + currentMinutes;

    var currentTimeString = currentHours + ":" + currentMinutes;

    document.getElementById("clock").firstChild.nodeValue = currentTimeString;
    setTimeout("updateClock()", 60000)
}

var searchBuffer =
{
    bufferText: false,
    bufferTime: 300,

    modified: function (strId) {
        setTimeout('searchBuffer.compareBuffer("' + strId + '","' + xajax.$(strId).value + '");', this.bufferTime);
    },

    compareBuffer: function (strId, strText) {
        if (strText == xajax.$(strId).value && strText != this.bufferText) {
            this.bufferText = strText;
            searchBuffer.makeRequest(strId);
        }
    },

    makeRequest: function (strId) {
        xajax_doAjaxSearch(document.getElementById('searchphrase').value, document.getElementById('searchtype').value);
    }
}

var millionBillion = function (isk) {
    if (isk > 1000000000) { // Billion
        return (isk / 1000000000).toFixed(2) + "b ISK";
    }
    return (isk / 1000000).toFixed(2) + "m ISK";
};

var truncate = function (string, length, nodot) {
    if(nodot == false) {
        return string.length > length ? string.substring(0, length - 3) + "..." : string;
    } else {
        return string.length > length ? string.substring(0, length - 3) : string;
    }
};

var turnOnFunctions = function () {
    $(".tooltips").scojs_tooltip();

    // Clickable Row
    var ctrlTriggered = false;
    $("body").keydown(function(e) {
        if(e.which == 17) {
            ctrlTriggered = true;
        }
    });
    $("body").keyup(function(e) {
        if(e.which == 17) {
            ctrlTriggered = false;
        }
    });
    $(".clickableRow").click(function(e) {
        e.preventDefault();
        var buttonClicked = e.which;
        if(buttonClicked == 1 && ctrlTriggered == false) {
            window.location = $(this).data("href");
        } else if(buttonClicked == 1 && ctrlTriggered == true) {
            window.open($(this).data("href"), "_blank");
        }
    });
};

var largestID = function (oldID, newID) {
    return Math.max(oldID, newID);
};

var isOdd = function(number) {
    return number % 2;
}

var inArray = function(haystack, needle) {
    if(typeof haystack == "string")
        haystack = [haystack];

    return (haystack.indexOf(needle) > -1);
};

function format(x) {
    return isNaN(x)?"":x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
