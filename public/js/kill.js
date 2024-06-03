var generateKillData = function(killID) {
    // Get Data
    // Turn on CORS support for jQuery
    jQuery.support.cors = true;

    // Define the current origin url (eg: https://evekill.club)
    var currentOrigin = window.location.origin;
    var killURL = currentOrigin + "/api/kill/killID/" + killID;

    // Get the data from the JSON API and output it as a killlist...
    $.ajax({
        // Define the type of call this is
        type: "GET",
        // Define the url we're getting data from
        url: killURL,
        // Predefine the data field.. it's just an empty array
        data: "{}",
        // Define the content type we're getting
        contentType: "application/json; charset=utf-8",
        // Set the data type to json
        dataType: "json",
        success: function (data) {
            if(data.victim.shipGroupName == undefined)
                data.victim.shipGroupName = "";

            pointHTML(data);
            topDamageDealerAndFinalBlow(data);
            topVictimInformationBox(data);
            fittingWheel(data);
            itemDetail(data);
            involvedPartiesInfo(data);
            involvedPartiesList(data);
            comments();
        }
    });

    // Generate Point HTML
    var pointHTML = function(data) {
        var h =
            '<table class="kb-table kb-box">' +
                '<tr>' +
                    '<td class="kb-table-header">Points</td>' +
                '</tr>' +
                '<tr class="kb-table-row-even">' +
                    '<td>' +
                        '<div class="menu-wrapper">' +
                            '<div class="kill-points">' + Math.round(data.pointValue) + '</div>' +
                        '</div>' +
                    '</td>' +
                '</tr>' +
            '</table>';

        $("#points").append(h);
    };
    var topDamageDealerAndFinalBlow = function(data) {
        var h = "";
        var loop = 0;
        Object.values(data.attackers).forEach(function (attacker) {
            // On the first loop, we get the top damage dealer, right off the bat
            if (loop == 0) {
                h +=
                    '<table class="kb-table kb-box">' +
                    '<tr>' +
                    '<td class="kb-table-header" colspan="2">Top Damage Dealer</td>' +
                    '</tr>' +
                    '<tr class="kb-table-row-odd">' +
                        '<td class="finalblow">' +
                            '<div class="menu-wrapper">' +
                                '<a href="/character/'+attacker.characterID+'">' +
                                    '<img class="finalblow rounded" data-trigger="tooltip" data-delay="0" data-content="'+attacker.characterName+'" data-position="s" src="https://imageserver.eveonline.com/Character/'+attacker.characterID+'_64.jpg" alt="'+attacker.characterName+'" title="'+attacker.characterName+'" />' +
                                '</a>' +
                            '</div>' +
                        '</td>' +
                        '<td class="finalblow">' +
                            '<div class="menu-wrapper">' +
                                '<a href="/ship/'+attacker.shipTypeID+'">' +
                                    '<img class="finalblow rounded" data-trigger="tooltip" data-delay="0" data-content="'+attacker.shipTypeName+'" data-position="s" src="https://imageserver.eveonline.com/Type/'+attacker.shipTypeID+'_64.png" alt="'+attacker.shipTypeName+'" title="'+attacker.shipTypeName+'" />' +
                                '</a>' +
                            '</div>' +
                        '</td>' +
                    '</tr>' +
                    '</table>';
            }

            // FinalBlow
            if (attacker.finalBlow == 1) {
                h +=
                    '<table class="kb-table kb-box">' +
                        '<tr>' +
                            '<td class="kb-table-header" colspan="2">Final Blow</td>' +
                        '</tr>' +
                        '<tr class="kb-table-row-odd">' +
                            '<td class="finalblow">' +
                                '<div class="menu-wrapper">' +
                                    '<a href="/character/'+attacker.characterID+'">' +
                                        '<img class="finalblow rounded" data-trigger="tooltip" data-delay="0" data-content="'+attacker.characterName+'" data-position="s" src="https://imageserver.eveonline.com/Character/'+attacker.characterID+'_64.jpg" alt="'+attacker.characterName+'" title="'+attacker.characterName+'" />' +
                                    '</a>' +
                                '</div>' +
                            '</td>' +
                            '<td class="finalblow">' +
                                '<div class="menu-wrapper">' +
                                    '<a href="/ship/'+attacker.shipTypeID+'">' +
                                        '<img class="finalblow rounded" data-trigger="tooltip" data-delay="0" data-content="'+attacker.shipTypeName+'" data-position="s" src="https://imageserver.eveonline.com/Type/'+attacker.shipTypeID+'_64.png" alt="'+attacker.shipTypeName+'" title="'+attacker.shipTypeName+'" />' +
                                    '</a>' +
                                '</div>' +
                            '</td>' +
                        '</tr>' +
                    '</table>';
            }
            loop++;
        });

        $("#topDamageAndFinal").append(h);
    };
    var topVictimInformationBox = function(data) {
        var h = "";
        h +=
            '<div class="kl-detail-vicpilot">' +
                '<table class="kb-table">' +
                    '<col class="logo"/>' +
                    '<col class="attribute-name"/>' +
                    '<col class="attribute-data"/>' +
                    '<tr class="kb-table-row-even" >' +
                        '<td class="logo" rowspan="3">' +
                            '<a href="/character/'+data.victim.characterID+'"><img class="rounded" data-trigger="tooltip" data-delay="0" data-content="'+data.victim.characterName+'" data-position="s" src="https://imageserver.eveonline.com/Character/' + data.victim.characterID + '_64.jpg" alt="' + data.victim.characterName + '"/></a>' +
                        '</td>';
                        if(data.victim.characterID > 0) {
                            h +=
                                '<td>Victim:</td>' +
                                '<td><a href="/character/' + data.victim.characterID + '">' + data.victim.characterName + '</a></td>';
                        } else {
                            h += '<td>&nbsp;</td><td>&nbsp;</td>';
                        }
                    h += '</tr>' +
                    '<tr class="kb-table-row-odd">' +
                        '<td>Corporation:</td>' +
                        '<td><a href="/corporation/' + data.victim.corporationID + '">' + data.victim.corporationName + '</a>';
                        if(data.victim.factionID > 0) {
                            h += '(<a href="/faction/' + data.victim.factionID + '">' + data.victim.factionName + '</a>)</td>';
                        }
                        if(data.victim.allianceID > 0) {
                            h += '</tr>' +
                                '<tr class="kb-table-row-even">' +
                                '<td>Alliance:</td>' +
                                '<td><a href="/alliance/' + data.victim.allianceID + '">' + data.victim.allianceName + '</a></td>' +
                                '</tr>';
                        } else {
                            h += '<td>&nbsp;</td><td>&nbsp;</td>';
                        }
                h += '</table>' +
            '</div>';

        h +=
            '<div class="kl-detail-vicship">' +
                '<table class="kb-table">' +
                    '<col class="logo"/>' +
                    '<col class="attribute-name"/>' +
                    '<col class="attribute-data"/>' +
                    '<tr class="kb-table-row-even" >' +
                        '<td class="logo" rowspan="3">' +
                            '<a href="/ship/'+data.victim.shipTypeID+'"><img class="rounded" data-trigger="tooltip" data-delay="0" data-content="'+data.victim.shipTypeName+'" data-position="s" src="https://imageserver.eveonline.com/Type/' + data.victim.shipTypeID + '_64.png" alt="' + data.victim.shipTypeName + '"/></a>' +
                        '</td>' +
                        '<td>Ship:</td>' +
                        '<td><a href="/ship/' + data.victim.shipTypeID + '">' + data.victim.shipTypeName + '</a> (<a href="/group/'+data.victim.shipGroupID+'/">' + data.victim.shipGroupName.trim() + '</a>)</td>' +
                    '</tr>' +
                    '<tr class="kb-table-row-odd">' +
                        '<td>Location:</td>' +
                        '<td><a href="/system/' + data.solarSystemID + '">' + data.solarSystemName + '</a> (<a href="/region/' + data.regionID + '/">' + data.regionName + '</a>)</td>' +
                    '</tr>' +
                    '<tr class="kb-table-row-even">' +
                        '<td>Date:</td>' +
                        '<td>' + data.killTime_str + '</td>' +
                    '</tr>' +
                    '<tr class="kb-table-row-odd">' +
                        '<td colspan="2">ISK Loss at time of kill:</td>' +
                        '<td>' + millionBillion(data.totalValue) + '</td>' +
                    '</tr>' +
                    '<tr class="kb-table-row-even">' +
                        '<td colspan="2">Total Damage Taken:</td>' +
                        '<td>' + format(Math.round(data.victim.damageTaken)) + '</td>' +
                    '</tr>' +
                    '<tr class="kb-table-row-odd">' +
                        '<td colspan="2">Near:</td>' +
                        '<td>' + data.near + '</td>' +
                    '</tr>' +
                '</table>' +
            '</div>';

        $("#topVictimInfo").append(h);
    };
    var fittingWheel = function(data) {
        var h = "";

        h += '' +
        '<div class="kl-detail-fitting">' +
            '<div class="fitting-panel" style="position:relative; height:398px; width:398px;">' +
            '<div id="mask" class="fit-slot-bg">' +
            '<img style="height:398px; width:398px;" src="/panel/tyrannis.png" alt="" /></div>';

        var slotTypes = {
            "highx": {"flags": [27, 28, 29, 30, 31, 32, 33, 34], "styles": {
                27: 'left:73px; top:60px;',
                28: 'left:102px; top:42px;',
                29: 'left:134px; top:27px;',
                30: 'left:169px; top:21px;',
                31: 'left:203px; top:22px;',
                32: 'left:238px; top:30px;',
                33: 'left:270px; top:45px;',
                34: 'left:295px; top:64px;'
            }, "ammo": {
                27: 'left:94px; top:88px;',
                28: 'left:119px; top:70px;',
                29: 'left:146px; top:58px;',
                30: 'left:175px; top:52px;',
                31: 'left:204px; top:52px;',
                32: 'left:232px; top:60px;',
                33: 'left:258px; top:72px;',
                34: 'left:280px; top:91px;'
            }},
            "midx": {"flags": [19, 20, 21, 22, 23, 24, 25, 26], "styles": {
                19: 'left:26px; top:140px;',
                20: 'left:24px; top:176px;',
                21: 'left:23px; top:212px;',
                22: 'left:30px; top:245px;',
                23: 'left:46px; top:278px;',
                24: 'left:69px; top:304px;',
                25: 'left:100px; top:328px;',
                26: 'left:133px; top:342px;'
            }, "ammo": {
                19: 'left:59px; top:154px;',
                20: 'left:54px; top:182px;',
                21: 'left:56px; top:210px;',
                22: 'left:62px; top:238px;',
                23: 'left:76px; top:265px;',
                24: 'left:94px; top:288px;',
                25: 'left:118px; top:305px;',
                26: 'left:146px; top:318px;'
            }},
            "lowx": {"flags": [11, 12, 13, 14, 15, 16, 17, 18], "styles": {
                11: 'left:344px; top:143px;',
                12: 'left:350px; top:178px;',
                13: 'left:349px; top:213px;',
                14: 'left:340px; top:246px;',
                15: 'left:323px; top:277px;',
                16: 'left:300px; top:304px;',
                17: 'left:268px; top:324px;',
                18: 'left:234px; top:338px;'
            }, "ammo": {
                11: 'left:314px; top:138px;',
                12: 'left:320px; top:173px;',
                13: 'left:319px; top:205px;',
                14: 'left:310px; top:241px;',
                15: 'left:293px; top:272px;',
                16: 'left:270px; top:299px;',
                17: 'left:238px; top:319px;',
                18: 'left:204px; top:333px;'
            }},
            "rigxx": {"flags": [92, 93, 94, 95, 96, 97, 98, 99], "styles": {
                92: 'left:148px; top:259px;',
                93: 'left:185px; top:267px;',
                94: 'left:221px; top:259px;'
            }},
            "subx": {"flags": [125, 126, 127, 128, 129, 130, 131, 132], "styles": {
                125: 'left:117px; top:131px;',
                126: 'left:147px; top:108px;',
                127: 'left:184px; top:98px;',
                128: 'left:221px; top:107px;',
                129: 'left:250px; top:131px;'
            }}
        };

        for(var key in slotTypes) {
            var highCount = 0;
            var medCount = 0;
            var lowCount = 0;
            var rigCount = 0;
            var subCount = 0;
            var slotName = key;
            var d = slotTypes[key];
            var flags = d["flags"];
            var style = d["styles"];
            var ammo = {};
            if(d["ammo"] != null)
                ammo = d["ammo"];

            for(var slotKey in Object.values(data.items)) {
                var item = data.items[slotKey];
                var ammoCategory = 8;
                var itemCategory = 7;

                if (inArray(flags, item.flag) && item.categoryID == itemCategory) {
                    if (slotName == "highx") {
                        highCount++;
                    } else if (slotName == "midx") {
                        medCount++;
                    } else if (slotName == "lowx") {
                        lowCount++;
                    } else if (slotName == "rigxx") {
                        rigCount++;
                    } else if (slotName == "subx") {
                        subCount++;
                    }
                }
            }

            h += '<div id="'+slotName+'" class="fit-slot-bg">';
            if(slotName == "highx") {
                h += '<img src="/panel/'+highCount+'h.png" alt="" />';
            } else if(slotName == "midx") {
                h += '<img src="/panel/'+medCount+'m.png" alt="" />';
            } else if(slotName == "lowx") {
                h += '<img src="/panel/'+lowCount+'l.png" alt="" />';
            } else if(slotName == "rigxx") {
                h += '<img src="/panel/'+rigCount+'r.png" alt="" />';
            } else if(slotName == "subx") {
                h += '<img src="/panel/'+subCount+'s.png" alt="" />';
            }
            h += '</div>';

            for(var slotKey in Object.values(data.items)) {
                var item = data.items[slotKey];
                var ammoCategory = 8;
                var itemCategory = 7;

                if(inArray(flags, item.flag) && item.categoryID == itemCategory) {
                    if(item.qtyDestroyed > 0) {
                        h += '<div data-trigger="tooltip" data-delay="0" data-content="'+item.typeName+'" data-position="s" id="' + slotName + '" class="fit-module fit-destroyed" style="' + style[item.flag] + '"><img src="https://imageserver.eveonline.com/Type/' + item.typeID + '_32.png"></div>';
                    } else {
                        h += '<div data-trigger="tooltip" data-delay="0" data-content="'+item.typeName+'" data-position="s" id="' + slotName + '" class="fit-module" style="' + style[item.flag] + '"><img src="https://imageserver.eveonline.com/Type/' + item.typeID + '_32.png"></div>';
                    }
                } else if (inArray(flags, item.flag) && item.categoryID == ammoCategory) {
                    h += '<div data-trigger="tooltip" data-delay="0" data-content="'+item.typeName+'" data-position="s" id="' + slotName + '" class="fit-module" style="' + ammo[item.flag] + '"><img src="https://imageserver.eveonline.com/Type/' + item.typeID + '_32.png"></div>';
                }
            }
        }

        h +=
            '<div class="bigship"><img src="https://imageserver.eveonline.com/Render/'+data.victim.shipTypeID+'_256.png" alt="" /></div>';

        h += '</div>' +
        '</div>';

        $("#fittingWheel").append(h);
    };
    var itemDetail = function (data) {
        // {typeID, typeName, qty, value
        var reduced = Object.values(data.items).reduce(function (pv, cv) {
            if (cv.qtyDropped > 0) {
                if (pv[cv.typeName + "dropped"]) {
                    pv[cv.typeName + "dropped"]["qtyDropped"] += cv.qtyDropped;
                    pv[cv.typeName + "dropped"]["value"] += (cv.value * cv.qtyDropped);
                } else {
                    pv[cv.typeName + "dropped"] = [];
                    pv[cv.typeName + "dropped"]["flag"] = cv.flag;
                    pv[cv.typeName + "dropped"]["typeID"] = cv.typeID;
                    pv[cv.typeName + "dropped"]["typeName"] = cv.typeName;
                    pv[cv.typeName + "dropped"]["qtyDropped"] = cv.qtyDropped;
                    pv[cv.typeName + "dropped"]["qtyDestroyed"] = 0;
                    pv[cv.typeName + "dropped"]["value"] = (cv.value * cv.qtyDropped);
                }
            } else if (cv.qtyDestroyed > 0) {
                if (pv[cv.typeName + "destroyed"]) {
                    pv[cv.typeName + "destroyed"]["qtyDestroyed"] += cv.qtyDestroyed;
                    pv[cv.typeName + "destroyed"]["value"] += (cv.value * cv.qtyDestroyed);
                } else {
                    pv[cv.typeName + "destroyed"] = [];
                    pv[cv.typeName + "destroyed"]["flag"] = cv.flag;
                    pv[cv.typeName + "destroyed"]["typeID"] = cv.typeID;
                    pv[cv.typeName + "destroyed"]["typeName"] = cv.typeName;
                    pv[cv.typeName + "destroyed"]["qtyDropped"] = 0;
                    pv[cv.typeName + "destroyed"]["qtyDestroyed"] = cv.qtyDestroyed;
                    pv[cv.typeName + "destroyed"]["value"] = (cv.value * cv.qtyDestroyed);
                }
            }
            return pv;
        }, {});

        var slotPopulated = function (flags, item) {
            if (inArray(flags, item.flag)) {
                return true;
            }
            return false;
        };
        var h = "";
        var slotTypes = {
            "High Slot": [27, 28, 29, 30, 31, 32, 33, 34],
            "Medium Slot": [19, 20, 21, 22, 23, 24, 25, 26],
            "Low Slot": [11, 12, 13, 14, 15, 16, 17, 18],
            "Rig Slot": [92, 93, 94, 95, 96, 97, 98, 99],
            "Subsystem": [125, 126, 127, 128, 129, 130, 131, 132],
            "Drone Bay": [87],
            "Cargo Bay": [5],
            "Fuel Bay": [133],
            "Fleet Hangar": [155],
            "Fighter Bay": [158],
            "Fighter Launch Tubes": [159, 160, 161, 162, 163],
            "Ship Hangar": [90],
            "Ore Hold": [134],
            "Gas hold": [135],
            "Mineral hold": [136],
            "Salvage Hold": [137],
            "Ship Hold": [138],
            "Small Ship Hold": [139],
            "Medium Ship Hold": [140],
            "Large Ship Hold": [141],
            "Industrial Ship Hold": [142],
            "Ammo Hold": [143],
            "Quafe Bay": [154],
            "Structure Services": [164, 165, 166, 167, 168, 169, 170, 171],
            "Structure Fuel": [172],
            "Implants": [89]
        };
        var lossValue = 0;
        var dropValue = 0;
        h +=
            '<div class="kl-detail-shipdetails">' +
            '<div class="block-header">Ship details</div>' +
                '<table class="kb-table">';

                for (var key in slotTypes) {
                    var slotName = key;
                    var flags = slotTypes[key];
                    for(var slotKey in Object.values(data.items)) {
                        var item = data.items[slotKey];
                        if(slotPopulated(flags, item)) {
                            h += '<tr class="kb-table-row-evenslotbg">' +
                                '<th class="item-icon"></th>' +
                                '<th colspan="2"><b>' + slotName + '</b> </th>' +
                                '<th><b>Value</b></th>' +
                                '</tr>';
                            break;
                        }
                    }

                    for(var key in reduced) {
                        var item = reduced[key];
                        if (inArray(flags, item.flag)) {
                            if (item.qtyDropped > 0) {
                                h +=
                                    '<tr class="kb-table-row-odd dropped">' +
                                    '<td class="item-icon"><a href="/type/' + item.typeID + '"><img data-trigger="tooltip" data-delay="0" data-content="'+item.typeName+'" data-position="e" src="https://imageserver.eveonline.com/Type/' + item.typeID + '_32.png"</a></td>' +
                                    '<td>' + item.typeName + '</td>' +
                                    '<td>' + item.qtyDropped + '</td>' +
                                    '<td>' + millionBillion(item.value) + '</td>' +
                                    '</tr>';

                                dropValue += item.value;
                            } else if (item.qtyDestroyed > 0) {
                                h +=
                                    '<tr class="kb-table-row-odd">' +
                                    '<td class="item-icon"><a href="/type/' + item.typeID + '"><img data-trigger="tooltip" data-delay="0" data-content="'+item.typeName+'" data-position="e" src="https://imageserver.eveonline.com/Type/' + item.typeID + '_32.png"</a></td>' +
                                    '<td>' + item.typeName + '</td>' +
                                    '<td>' + item.qtyDestroyed + '</td>' +
                                    '<td>' + millionBillion(item.value) + '</td>' +
                                    '</tr>';

                                lossValue += item.value;
                            }
                        }
                    }
                }
                h +=
                '<tr class="kb-table-row-even summary itemloss">' +
                    '<td colspan="3"><div>Total Module Loss:</div></td>' +
                    '<td>' + millionBillion(lossValue) + '</td>' +
                '</tr>' +
                '<tr class="kb-table-row-odd summary itemdrop">' +
                    '<td colspan="3"><div>Total Module Drop:</div></td>' +
                    '<td>' + millionBillion(dropValue) + '</td>' +
                '</tr>' +
                '<tr class="kb-table-row-even summary itemdrop">' +
                    '<td colspan="3"><div>Total Fit Value:</div></td>' +
                    '<td>' + millionBillion(data.fittingValue) + '</td>' +
                '</tr>' +
                '<tr class="kb-table-row-odd summary shiploss">' +
                    '<td colspan="3"><div>Ship Loss:</div></td>' +
                    '<td>' + millionBillion(data.shipValue) + '</td>' +
                '</tr>' +
                '<tr class="kb-table-row-even summary totalloss">' +
                    '<td colspan="3">Total Loss at current prices:</td>' +
                    '<td>' + millionBillion(data.totalValue) + '</td>' +
                '</tr>' +
            '</table>' +
        '</div>';

        $("#itemDetail").append(h);

    };
    var involvedPartiesInfo = function(data) {
        var h = "";
        var attackerCount = Object.values(data.attackers).length;
        var sortObject = function(obj) {
            var arr = [];
            for(var prop in obj) {
                if(prop != "") {
                    if (obj.hasOwnProperty(prop)) {
                        arr.push({"key": prop, "value": obj[prop]});
                    }
                }
            }

            arr.sort(function(a, b) { return b.value - a.value; });
            return arr;
        };
        var invAlliances = sortObject(Object.values(data.attackers).reduce((p, c) => !(p[c.allianceName] ? p[c.allianceName]++ : p[c.allianceName] = 1) || p, {}));
        var invCorporations = sortObject(Object.values(data.attackers).reduce((p, c) => !(p[c.corporationName] ? p[c.corporationName]++ : p[c.corporationName] = 1) || p, {}));
        var invShips = sortObject(Object.values(data.attackers).reduce((p, c) => !(p[c.shipTypeName] ? p[c.shipTypeName]++ : p[c.shipTypeName] = 1) || p, {}));
        if(attackerCount > 4) {
            h +=
                '<div class="kl-detail-invsum">' +
                '<div class="involvedparties">' + attackerCount + ' Involved parties' +
                '<div id="invsumcollapse" style="float: right">' +
                '<a href="javascript:Toggle();">Show/Hide</a>' +
                '</div>' +
                '</div>';
            h +=
                '<div id="ToggleTarget">' +
                '<table class="kb-table">' +
                '<tr class="kb-table-row-even" >' +
                '<td class="invcorps">' +
                '<div class="no_stretch">';
                for(var key in invAlliances) {
                    var amount = invAlliances[key]["value"];
                    var name = invAlliances[key]["key"];

                    h += '<div class="kb-inv-parties">' +
                        '('+ amount +') '+ name +
                        '</div>';

                }

                for(var key in invCorporations) {
                    var amount = invCorporations[key]["value"];
                    var name = invCorporations[key]["key"];

                    h += '<div>' +
                        '&nbsp;('+ amount +') '+ name +
                        '</div>';

                }

            h += '<td class="invships">' +
                '<div class="no_stretch">';
                for(var key in invShips) {
                    var amount = invShips[key]["value"];
                    var name = invShips[key]["key"];

                    h += '('+amount+') '+name+' <br/>';
                }
                h +=
                    '</div>' +
                    '</td>' +
                    '</div>' +
                    '</td>' +
                    '</tr>' +
                    '</table>' +
                    '</div>' +
                    '</div>';

            $("#involvedPartiesInfo").append(h);
        }

    };
    var involvedPartiesList = function(data) {
        var h = "";
        var attackerCnt = 0;
        var totalDamage = data.victim.damageTaken;
        var html = function(attacker, odd) {
            if(attacker.shipGroupName == undefined)
                attacker.shipGroupName = "";

            var h = "";
            var classText = "kb-table-row-even";
            if(odd == true) {
                classText = "kb-table-row-odd";
            }
            h +=
            '<tr class="' + classText + '">' +
                '<td rowspan="5" class="logo" width="64">' +
                    '<a href="/character/' + attacker.characterID + '">';
                        if(attacker.finalBlow == true) {
                            h += '<img class="finalblow rounded" data-trigger="tooltip" data-delay="0" data-content="'+attacker.characterName+'" data-position="s" src="https://imageserver.eveonline.com/Character/'+ attacker.characterID+'_64.jpg" title="' + attacker.characterName + '" alt="' + attacker.characterName + '" />';
                        } else {
                            h += '<img class="rounded" data-trigger="tooltip" data-delay="0" data-content="'+attacker.characterName+'" data-position="s" src="https://imageserver.eveonline.com/Character/'+ attacker.characterID+'_64.jpg" title="' + attacker.characterName + '"  alt="' + attacker.characterName + '" />';
                        }
            h +=
                    '</a>' +
                '</td>' +
                '<td rowspan="5" class="logo" width="64">' +
                    '<a href="/ship/'+attacker.shipTypeID+'">';
                        if(attacker.finalBlow == true) {
                            h += '<img class="finalblow rounded" data-trigger="tooltip" data-delay="0" data-content="'+attacker.shipTypeName+'" data-position="s" src="https://imageserver.eveonline.com/Type/'+attacker.shipTypeID+'_64.png" alt="'+attacker.shipTypeName+'" title="'+attacker.shipTypeName+'" />';
                        } else {
                            h += '<img class="rounded" data-trigger="tooltip" data-delay="0" data-content="'+attacker.shipTypeName+'" data-position="s" src="https://imageserver.eveonline.com/Type/'+attacker.shipTypeID+'_64.png" alt="'+attacker.shipTypeName+'" title="'+attacker.shipTypeName+'" />';
                        }
            h +=    '</a>' +
                '</td>' +
                '<td>' +
                    '<a href="/corporation/' + attacker.corporationID+ '"><b>' + attacker.corporationName + '</b></a>' +
                '</td>' +
            '</tr>' +
            '<tr class="' + classText + '">' +
                '<td>' +
                    '<a href="/alliance/' + attacker.allianceID + '">' + attacker.allianceName + '</a>' +
                '</td>' +
            '</tr>' +
            '<tr class="' + classText + '">' +
                '<td>' +
                    '<a href="/ship/' + attacker.shipTypeID + '"><b>' + attacker.shipTypeName + '</b></a> (<a href="/group/'+attacker.shipGroupID+'/">' + attacker.shipGroupName.trim() + '</a>)' +
                '</td>' +
            '</tr>' +
            '<tr class="' + classText + '">' +
                '<td><a href="/type/' + attacker.weaponTypeID + '">' + attacker.weaponTypeName + '</a></td>' +
            '</tr>' +
            '<tr class="' + classText + '">' +
                '<td>Damage done: <span style="color: #e42f2f;"><b>' + format(Math.round(attacker.damageDone)) + '</b></span> (<span style="color: #3d9e2f; ">' + parseFloat((attacker.damageDone / totalDamage) * 100).toFixed(2) + '%</span>)</td>' +
            '</tr>';

            return h;
        };
        h += '<table class="kb-table" width="380" border="0" cellspacing="1">';
      Object.values(data.attackers).forEach(function(attacker) {
            h +=
                '<tr class="kill-pilot-name">' +
                    '<td colspan="3">' +
                        '<a href="/character/' + attacker.characterID + '">'+ attacker.characterName+'</a>' +
                    '</td>' +
                '</tr>' +
                '<col class="logo" />' +
                '<col class="logo" />' +
                '<col class="attribute-data" />';

            if(isOdd(attackerCnt)) {
                h += html(attacker, true);
            } else {
                h += html(attacker, false);
            }

            attackerCnt++;
        });

        h += "</table>";
        $("#InvolvedPartiesList").append(h);

    };
    var comments = function() {
      var currentOrigin = window.location.origin;
      var killURL = currentOrigin + "/api/comments/get/" + killID;
      this.killID = killID;

      $.ajax({
        type: "GET",
        url: killURL,
        data: "{}",
        contentType: "application/json; charset=utf-8",
        dataType: "json",
        success: function (data) {
          var comment =
            '<div class="kl-detail-comments">' +
            '<div class="block-header2">Comments</div>' +
            '<table class="kb-table">' +
            '<tbody>' +
            '<tr>' +
            '<td class="kl-detail-comments-outer">' +
            '<table class="kl-detail-comments-inner">' +
            '<tbody>';

            Object.values(data).forEach(function(c) {
              comment += '<tr><td><div id="kl-detail-comment-list"><div class="comment-posted">' +
                '<div class="name">' + c['name'] + ':</div>' +
                '<p>' + c['comment'] + '</p></div></div></td></tr>';
            });

            comment +=
              '<tr>' +
              '<td>' +
              '<form>' +
              '<table>' +
              '<tbody>' +
              '<tr>' +
              '<td>' +
              '<textarea class="comment" name="comment" cols="55" rows="5" style="width:97%"></textarea>' +
              '</td>' +
              '</tr>' +
              '<tr>' +
              '<td>' +
              '<br>' +
              '<b>Name:</b>' +
              '<input style="position:relative; right:-3px;" class="comment-button" name="name" type="text" size="24" maxlength="24">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' +
              '<input class="comment-button" name="submit" type="button" onclick="submitComment()" value="Add Comment">'+
              '</td>'+
              '</tr>'+
              '</tbody>'+
              '</table>'+
              '</form>'+
              '</td>'+
              '</tr>' +
              '</tbody>' +
              '</table>' +
              '</td>' +
              '</tr>' +
              '</tbody>' +
              '</table>' +
              '</div>';
          $("#Comments").append(comment);
        }.bind(this)
      });
    };
};

var submitComment = function() {
  var name = $(".comment-button").val();
  var comment = $(".comment").val();

  var currentOrigin = window.location.origin;
  var killURL = currentOrigin + "/api/comments/get/" + killID;
  this.killID = killID;

  $.post("/api/comments/post", {
      name: name,
      comment: comment,
      killID: killID
    }
  );
};
