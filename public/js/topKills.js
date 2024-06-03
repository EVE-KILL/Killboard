var generateTopKillsHeader = function (kill) {
    var h = "";

    h += '<th data-trigger="tooltip" data-delay="0" data-position="s" data-cssclass="infotip" data-content="';

    if (kill.victim.character_id > 0) {
        h += '<img class=\'rounded\' src=https://imageserver.eveonline.com/Character/' + kill.victim.character_id + '_128.jpg/><br>';
        h += 'Character: ' + kill.victim.character_name + '<br>';
    } else {
        h += '<img class=\'rounded\' src=https://imageserver.eveonline.com/Corporation/' + kill.victim.corporation_id + '_128.png/><br>';
    }

    h += 'Corporation: ' + kill.victim.corporation_name + '<br>';

    if (kill.victim.alliance_id > 0) {
        h += 'Alliance: ' + kill.victim.alliance_name + '<br>';
    }
    h +=
        'System: ' + kill.system_name + '<br>' +
        'Near: ' + kill.near + '<br>' +
        'Region: ' + kill.region_name + '<br>' +
        'Fitting Value: ' + millionBillion(kill.fitting_value) + '<br>' +
        'Ship value: ' + millionBillion(kill.ship_value) + '<br>"' +
    '">' +
    '<a href="/kill/'+kill.killmail_id+'"><img class="rounded" src="https://imageserver.eveonline.com/Render/' + kill.victim.ship_id + '_128.png"><br>' + kill.victim.ship_name + '<br>' + millionBillion(kill.total_value) + '</a>' +
    '</th>';

    return h;
    /*var trHTML = "";


     // The HTML to populate pr. top kill
     trHTML += '' +
     '<div style="text-align: center; height: 140px;" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="bottom" data-html="true" ' +
     'data-content="">' +
     '<a href="/kill/' + kill.killID + '"><img class="img-circle" src="https://imageserver.eveonline.com/Render/' + kill.victim.shipTypeID + '_128.png" height="90"/></a>' +
     '<br/>' +
     '<span class="hidden-xs">' +
     '<a href="/character/' + kill.victim.character_id + '">' + truncate(kill.victim.shipTypeName, 20) + '</a>' +
     '<br/>' +
     '</span>' +
     '<h6>' + millionBillion(kill.totalValue) + '</h6>' +
     '<br/>' +
     '</div>';
     return trHTML;*/
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
    url: currentOrigin + "/api/stats/mostvaluablekillslast7days/6",
    // Predefine the data field.. it's just an empty array
    data: "{}",
    // Define the content type we're getting
    contentType: "application/json; charset=utf-8",
    // Set the data type to json
    dataType: "json",
    success: function (data) {
        var h = "";
        h +=
            '<table style="width: 100%;">' +
            '<tbody>' +
            '<tr>';
        // data-toggle='tooltip' data-html='true' data-placement='left' title='"+kill.killTime.toString()+"'
        // Now for each element in the data we just got from the json api, we'll build up some html.. ugly.. ugly.. html
        $.each(data, function (i, kill) {
            h += generateTopKillsHeader(kill); //This isn't exactly pretty - but it does the job for now... Until someone decides to cause an argument over it, and finally fixes it
        });

        h += '</tr>' +
            '</tbody>' +
            '</table>';

        // Append the killlist element to the killlist table
        $("#topKills").append(h);

        // Turn on tooltips, popovers etc.
        turnOnFunctions();
    }
});
