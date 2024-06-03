generateTopList = function(url, topListAppend, type, headerText) {
    var loop = 1;
    var generateHTML = function(data) {
        var h = '<table class="kb-table awardbox">' +
            '<tr>' +
            '<td class="kb-table-header">' + headerText + '</td>' +
            '</tr>' +
            '<tr class="kb-table-row-even">';

        var totalKills = 0;
        $.each(data, function (i, kill) {
            totalKills += kill.count;
        });

        $.each(data, function (i, kill) {
            var percent = (100 - ((kill.count / totalKills) * 100));
            if (loop == 1) {
                h += '<table class="kb-subtable awardbox-list">' +
                    '<tr>' +
                        '<td class="awardbox-num">1.</td>' +
                        '<td colspan="2">' +
                            '<a class="kb-shipclass" href="/'+type+'/'+kill.id + '">'+kill.name+'</a>' +
                        '</td>' +
                        '<td class="awardbox-count">' + kill.count + '</td>' +
                    '</tr>';
            } else {
                totalKills = totalKills - kill.count;
                h += '<tr>' +
                        '<td class="awardbox-num">'+loop+'.</td>' +
                        '<td colspan="2">' +
                            '<a class="kb-shipclass" href="/'+type+'/'+kill.id+'">' + kill.name + '</a>' +
                        '</td>' +
                        '<td class="awardbox-count">' + kill.count + '</td>' +
                    '</tr>';
            }
            loop++;
        });

        h += '<tr></tr><tr>' +
            '<td class="awardbox-comment" colspan="3">(Over last 30 days)</td>' +
            '</tr>' +
            '</table>' +
            '</td>' +
            '</tr>' +
            '</table>';

        return h;
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
        url: currentOrigin + url,
        // Predefine the data field.. it's just an empty array
        data: "{}",
        // Define the content type we're getting
        contentType: "application/json; charset=utf-8",
        // Set the data type to json
        dataType: "json",
        success: function (data) {
            var trHTML = "";
            if(data.length > 0) {
                trHTML += generateHTML(data); //This isn't exactly pretty - but it does the job for now... Until someone decides to cause an argument over it, and finally fixes it

                // Append the killlist element to the killlist table
                $("#" + topListAppend).append(trHTML);

                turnOnFunctions();
            }
        }
    });
};
