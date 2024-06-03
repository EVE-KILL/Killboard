var generateKillList = function (url, loadWebsocket, page, autoloadScroll) {
  var maxKillID = 0;
  var highestKillID = function (newID) {
    maxKillID = Math.max(maxKillID, newID);
    return maxKillID;
  };

  var generateKillList = function (kill, killCount) {
    var h = "";

    // Ship type portion
    if (isOdd(killCount)) {
      h += '<tr class="kb-table-row-kill kb-table-row-odd clickableRow" data-href="/kill/' + kill.killmail_id + '">';
    } else {
      h += '<tr class="kb-table-row-kill kb-table-row-even clickableRow" data-href="/kill/' + kill.killmail_id + '">';
    }
    h += '<td class="kb-table-imgcell">' +
      '<img class="rounded" data-trigger="tooltip" data-delay="0" data-content="' + kill.victim.ship_name + '" data-position="s" src="https://imageserver.eveonline.com/Type/' + kill.victim.ship_id + '_32.png" style="width: 32px; height: 32px;"/>' +
      '</td>' +
      '<td class="kl-shiptype-text">' +
      '<div class="no_stretch kl-shiptype-text">' +
      '<b>' + kill.victim.ship_name + '</b>' +
      '<br/>' +
      millionBillion(kill.total_value) +
      '</div>' +
      '</td>';

    // Victim portion
    if (kill.victim.alliance_id > 0) {
      h += '<td class="kb-table-imgcell">' +
        '<img class="rounded" data-trigger="tooltip" data-delay="0" data-content="' + kill.victim.alliance_name + '" data-position="s" src="https://imageserver.eveonline.com/Alliance/' + kill.victim.alliance_id + '_32.png" style="border: 0px; width: 32px; height: 32px;" title="' + kill.victim.alliance_name + '" alt="' + kill.victim.alliance_name + '"/>' +
        '</td>';
    } else {
      h += '<td class="kb-table-imgcell">' +
        '<img class="rounded" data-trigger="tooltip" data-delay="0" data-content="' + kill.victim.corporation_name + '" data-position="s" src="https://imageserver.eveonline.com/Corporation/' + kill.victim.corporation_id + '_32.png" style="border: 0px; width: 32px; height: 32px;" title="' + kill.victim.corporation_name + '" alt="' + kill.victim.corporation_name + '"/>' +
        '</td>';
    }

    h += '<td class="kl-victim-text">' +
      '<div class="no_stretch kl-victim-text">' +
      '<a href="/character/' + kill.victim.characterID + '"><b>' + kill.victim.character_name + '</b></a><br/>';

    if (kill.victim.alliance_id > 0) {
      h += '<a href="/alliance/' + kill.victim.alliance_id + '">' + kill.victim.alliance_name + '</a>';
    } else {
      h += '<a href="/corporation/' + kill.victim.corporation_id + '">' + kill.victim.corporation_name + '</a>';
    }

    // Final blow portion

    Object.values(kill.attackers).forEach(function (attacker) {
      if (attacker.final_blow == 1) {
        h += '<td class="kb-table-imgcell">' +
          '<img class="rounded" data-trigger="tooltip" data-delay="0" data-content="' + attacker.corporation_name + '" data-position="s" src="https://imageserver.eveonline.com/Corporation/' + attacker.corporation_id + '_32.png" style="border: 0px; width: 32px; height: 32px;" title="' + attacker.corporation_name + '" alt="' + attacker.corporation_name + '"/>' +
          '</td>' +
          '<td class="kl-finalblow">' +
          '<div class="no_stretch kl-finalblow">' +
          '<a href="/character/' + attacker.characterID + '"><b>' + attacker.character_name + '</b></a>' +
          '<br/>' +
          '<a href="/corporation/' + attacker.corporation_id + '">' + attacker.corporation_name + '</a>' +
          '</div>' +
          '</td>';
      }
    });

    // Location
    var attackerCount = Object.keys(kill.attackers).length;
    var date = new Date(kill.kill_time);
    var killTime = ("0" + date.getHours()).slice(-2) + ":" + ("0" + date.getMinutes()).slice(-2) + ":" + ("0" + date.getSeconds()).slice(-2);

    h += '<td class="kb-table-cell kl-location">' +
      '<div class="kl-location">' + kill.region_name + ', ' + kill.system_name + ' (' + parseFloat(kill.system_security).toFixed(2) + ')<br/>' +
      '</div>' +
      '<div class="kl-inv-comm">' +
      '<img src="/img/involved10_10.png" alt="I:"/> ' + attackerCount +
      '</div>' +
      '<div class="kl-date">' +
      '<a href="/related"><b>' + kill.kill_time_str + '</b></a>' +
      '</div>' +
      '</td>' +
      '</tr>';

    return h;
  };

  var webSocket = function (websocketUrl, prependTo, maxKillID) {
    ws = new WebSocket(websocketUrl);
    ws.onopen = function (event) {
      ws.send(JSON.stringify({'type': 'subscribe'}));
    };
    ws.onmessage = function (event) {
      var data = JSON.parse(event["data"]);
      if (data.killmail_id > maxKillID && (typeof data.kill_time_str != "undefined" && data.kill_time_str !== null)) {
        maxKillID = data.killmail_id;
        var trHTML = generateKillList(data);
        $(prependTo).prepend(trHTML);
        turnOnFunctions();
      }
    };
  };

  var loadMoreOnScroll = function (url, page) {
    page = parseInt(page);
    var isPreviousPageLoaded = true;

    $(window).scroll(function () {
      if ($(document).height() - 500 <= $(window).scrollTop() + $(window).height()) {
        if (isPreviousPageLoaded) {
          isPreviousPageLoaded = false;
          var address = window.location.origin + url + '/' + (page + 1);
          $.ajax({
            type: "GET",
            url: address,
            data: "{}",
            contentType: "application/json; charset=utf-8",
            dataType: "json",
            cache: false,
            success: function (data) {
              var trHTML = "";
              var killCount = 0;
              $.each(data, function (i, kill) {
                trHTML += generateKillList(kill, killCount);
                killCount++;
              });

              $("#killlist").append(trHTML);
              turnOnFunctions();
              page++;
              isPreviousPageLoaded = true;
            },
            error: function (msg) {
              alert(msg.responseText);
            }
          });
        }
      }
    });
  };

  // Turn on CORS support for jQuery
  jQuery.support.cors = true;

  // Define the current origin url (eg: https://evekill.club)
  var currentOrigin = window.location.origin;

  // Get the data from the JSON API and output it as a killlist...
  $.ajax({
    // Define the type of call this is
    type: "GET",
    // Define the url we're getting data from
    url: currentOrigin + url + '/' + page,
    // Predefine the data field.. it's just an empty array
    data: "{}",
    // Define the content type we're getting
    contentType: "application/json; charset=utf-8",
    // Set the data type to json
    dataType: "json",
    // Don't cache it - the backend does that for us
    cache: false,
    success: function (data) {
      var maxKillID = 0;
      var trHTML = "";
      // data-toggle='tooltip' data-html='true' data-placement='left' title='"+kill.killTime.toString()+"'
      // Now for each element in the data we just got from the json api, we'll build up some html.. ugly.. ugly.. html
      $.each(data, function (i, kill) {
        maxKillID = highestKillID(kill.killmail_id);
        trHTML += generateKillList(kill); //This isn't exactly pretty - but it does the job for now... Until someone decides to cause an argument over it, and finally fixes it
      });

      // Append the killlist element to the killlist table
      $("#killlist").append(trHTML);

      turnOnFunctions();
      if (loadWebsocket == true) {
        // Fire up the Websockets
        webSocket("wss://evedata.xyz/kills", "#killlist", maxKillID);
      }

      if (autoloadScroll == true) {
        // Turn on loading more on scroll
        loadMoreOnScroll(url, page);
      }
    }
  });
};
