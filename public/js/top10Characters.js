var top10ListGenerator = function (url, type) {
    // Turn on CORS support for jQuery
    jQuery.support.cors = true;

    // Define the current origin url (eg: https://evekill.club)
    var currentOrigin = window.location.origin;
    var loop = 1;

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
            var h = '<table class="kb-table awardbox">' +
                '<tr>' +
                '<td class="kb-table-header">'+type+'</td>' +
                '</tr>' +
                '<tr class="kb-table-row-even">' +
                '<td>';

            // data-toggle='tooltip' data-html='true' data-placement='left' title='"+kill.killTime.toString()+"'
            // Now for each element in the data we just got from the json api, we'll build up some html.. ugly.. ugly.. html
            var totalKills = 0;
            $.each(data, function (i, kill) {
                totalKills += kill.count;
            });

            $.each(data, function (i, kill) {
                percent = (100 - ((kill.count / totalKills) * 100));
                if (loop == 1) {
                    h += '<table class="kb-subtable awardbox-top">' +
                        '<tr class="kb-table-row-odd">' +
                        '<td><img class="rounded" data-trigger="tooltip" data-delay="0" data-position="e" data-content="' + kill.name + '" src="https://imageserver.eveonline.com/Character/' + kill.character_id + '_64.jpg" title="' + kill.name + '" alt="' + kill.name + '" height="64" width="64" /></td>' +
                        '<td><img class="rounded" src="/img/awards/eagle.png" alt="award" height="64" width="64" /></td>' +
                        '</tr>' +
                        '</table>' +
                        '<table class="kb-subtable awardbox-list">' +
                        '<tr>' +
                        '<td class="awardbox-num">1.</td>' +
                        '<td colspan="2"><a class="kb-shipclass" href="/character/'+kill.character_id+'" data-trigger="tooltip" data-cssclass="infotip" data-delay="0" data-position="e" data-content="' +
                        '<img class=\'rounded\' src=https://imageserver.eveonline.com/Character/' + kill.character_id + '_128.jpg/><br>' +
                        'Name: ' + kill.name + '<br>' +
                        'Corporation: ' + kill.corporation_name + '<br>';
                    if (kill.alliance_id > 0) {
                        h += 'Alliance: ' + kill.alliance_name + '<br>';
                    }
                    h += 'Kills: ' + kill.kills + '<br>' +
                        'Losses: ' + kill.losses + '<br>' +
                        'Points: ' + kill.points + '<br>"' +
                        '>' + kill.name + '</a></td>' +
                        '</tr>' +
                        '<tr>' +
                        '<td></td>' +
                        '<td>' +
                        '<div class="bar-background">' +
                        '<div class="bar" style="width: 100%;">&nbsp;</div>' +
                        '</div>' +
                        '</td>' +
                        '<td class="awardbox-count">' + kill.count + '</td>' +
                        '</tr>';
                } else {
                    totalKills = totalKills - kill.count;
                    h += '<tr>' +
                        '<td class="awardbox-num">'+loop+'</td>' +
                        '<td colspan="2"><a class="kb-shipclass" href="/character/'+kill.character_id+'" data-trigger="tooltip" data-cssclass="infotip" data-delay="0" data-position="e" data-content="' +
                        '<img class=\'rounded\' src=https://imageserver.eveonline.com/Character/' + kill.character_id + '_128.jpg/><br>' +
                        'Name: ' + kill.name + '<br>' +
                        'Corporation: ' + kill.corporation_name + '<br>';
                    if (kill.alliance_id > 0) {
                        h += 'Alliance: ' + kill.alliance_name + '<br>';
                    }
                    h += 'Kills: ' + kill.kills + '<br>' +
                        'Losses: ' + kill.losses + '<br>' +
                        'Points: ' + kill.points + '<br>"' +
                        '>' + kill.name + '</a></td>' +
                        '</tr>' +
                        '<tr>' +
                        '<td></td>' +
                        '<td><div class="bar-background"><div class="bar" style="width: ' + percent + '%;">&nbsp;</div></td>' +
                        '<td class="awardbox-count">' + kill.count + '</td>' +
                        '</tr>';
                }
                loop++;
            });

            h += '<tr>' +
                '<td class="awardbox-comment" colspan="3">(Kills over last 7 days)</td>' +
                '</tr>' +
                '</table>' +
                '</td>' +
                '</tr>' +
                '</table>'

            // Append the killlist element to the killlist table
            $("#topListCharacter").append(h);

            // Turn on tooltips, popovers etc.
            turnOnFunctions();
        }
    });
};

//@todo fix so that it unloads data when it gets over 1k items
top10ListGenerator("/api/stats/top10characters", "Top 10 Characters");
