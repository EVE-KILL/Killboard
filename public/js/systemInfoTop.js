var systemInfoTop = function(url) {
    var generateHTML = function(data) {
        var h = "";

        h +=
            '<table class="kb-table" style="width: auto;">' +
            '<colgroup>' +
            '<col class="logo">' +
            '<col class="attribute-name">' +
            '<col class="attribute-data">' +
            '</colgroup>' +
            '<tbody>' +
            '<tr class="kb-table-row-even">' +
            '<td rowspan="7">' +
            '<a href="/system/' + data.solarSystemID + '"><img class="rounded" src="https://images.evetech.net/types/' + data.solarSystemID + '/icon" alt="portrait"></a>' +
            '</td>';

        h += '</tr>' +
            '</tbody>' +
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

            trHTML += generateHTML(data); //This isn't exactly pretty - but it does the job for now... Until someone decides to cause an argument over it, and finally fixes it

            // Append the killlist element to the killlist table
            $("#info").append(trHTML);

            turnOnFunctions();
        }
    });
}
