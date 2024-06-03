var characterHistory = function(url, append) {
    var generateHTML = function(data) {
        var html = "";
        html += "<table class='kb-table'>";
        html += "<thead>";
        html += "<tr class='kb-table-header'>";
        html += "<td></td>";
        html += "<td>Name</td>";
        html += "<td>Joined</td>";
        html += "</tr>";
        html += "</thead>";
        html += "<tbody>";

        odd = false;
        data.history.forEach(function(corp) {
            html += "<tr class=";
            if(odd == true)
                html += "kb-table-row-odd";
            else
                html += "kb-table-row-even";
            html += ">";

            html += "<td class=kb-table-imgcell><img src='https://imageserver.eveonline.com/Corporation/" + corp.corporationID + "_32.png'/></td>";
            html += "<td style='text-align: center'><a href='/corporation/" + corp.corporationID +"'>" + corp.corporationName + "</a></td>";
            html += "<td>" + corp.startDate + "</td>";
            html += "</tr>";
            odd = odd == false;
        });
        html += "</tbody>";
        html += "</table>";

        return html;
    };

    jQuery.support.cors = true;
    var currentOrigin = window.location.origin;
    $.ajax({
        type: "GET",
        url: currentOrigin + url,
        data: "{}",
        contentType: "application/json; charset=utf-8",
        dataType: "json",
        success: function (data) {
            var trHTML = "";
            trHTML += generateHTML(data);
            $(append).append(trHTML);
            turnOnFunctions();
        }
    });
};
