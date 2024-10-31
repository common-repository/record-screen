(function (params) {

    function toHHMMSS (sec) {
        var sec_num = parseInt(sec, 10); // don't forget the second param
        var hours   = Math.floor(sec_num / 3600);
        var minutes = Math.floor((sec_num - (hours * 3600)) / 60);
        var seconds = sec_num - (hours * 3600) - (minutes * 60);

        if (hours   < 10) {hours   = "0"+hours;}
        if (minutes < 10) {minutes = "0"+minutes;}
        if (seconds < 10) {seconds = "0"+seconds;}
        return hours+':'+minutes+':'+seconds;
    }

    let page = 0;
    drawTable(page);

    function drawTable() {
        jQuery.ajax({
            url: params.ajaxurl,
            method: 'POST',
            data: {
                'action': 'screenRecorder_get_records',
                'nonce': params.nonce,
                'page': page
            },
            success: function (res) {
                if (!res) {
                    console.log('No data');
                    return;
                }
                let trs = '';
                let $spyTableContainer = jQuery('#spyTableContainer');
                $spyTableContainer.empty();
                res = JSON.parse(res);
                let numberPages = 1 + Math.floor((parseInt(res.numberRows) - 1) / parseInt(res.ROWS_PER_PAGE));
                let rows = res.rows;
                rows.forEach(function (data) {
                    trs += `<tr>
                                <td>${data['date']}</td>
                                <td>${data['ip']}</td>
                                <td>${toHHMMSS(data['duration'])}</td>
                                <td>${data['agent']}</td>
                                <td><a href="${data['url']}" target="_blank">${data['url']}</a></td>                                
                                <td>
                                    <button data-id="${data['id']}" data-spyaction="delete">Delete</button>
                                    <button data-uid="${data['uid']}" data-spyaction="play">Play</button>
                                </td>
                            </tr>`;
                });
                let table = `
                <table id="spyTable">
                <tr>
                    <th>Date</th>
                    <th>IP</th>
                    <th>Duration</th>
                    <th>Browser</th>
                    <th>url</th>
                    <th>Actions</th>
                </tr>
                ${trs}       
                </table>`;
                table += `
                <div>
                    <span>Total:<b>${res.numberRows}</b></span> `;
                    if (numberPages > 1) {
                        let options = '';
                        for (let i = 0; i < numberPages; i++) {
                            let selected = (parseInt(page) === i) ? 'selected' : '';
                            options += `<option value="${i}" ${selected} >${i + 1}</option>`;
                        }
                        table += `
                        <div>
                            Page:
                            <select id="spySelectPage">
                            ${options}
                            </select>
                         </div>                   
                </div>`;
                }
                $spyTableContainer.html(table);
            },
            error: function (errorThrown) {
                console.log(errorThrown);
            }
        });
    }

    jQuery(document).on('change', '#spySelectPage', function () {
        page = jQuery(this).val();
        drawTable();
    });

    jQuery(document).on('click', 'button[data-spyaction="delete"]', function () {
        if (confirm('Delete ?')) {
            let id = jQuery(this).data('id');
            jQuery.ajax({
                url: params.ajaxurl,
                method: 'POST',
                data: {
                    'id': id,
                    'action': 'screenRecorder_delete_record',
                    'nonce': params.nonce,
                },
                success: function () {
                    drawTable();
                },
                error: function (errorThrown) {
                    console.log(errorThrown);
                }
            });
        }
    });
    jQuery(document).on('click', '#spyTable [data-spyaction="play"]', function () {
        tb_show('Player', '#TB_inline', '');
        let uid = jQuery(this).data('uid');
        jQuery.ajax({
            url: params.ajaxurl,
            dataType: 'json',
            method: 'POST',
            data: {
                uid: uid,
                action: 'screenRecorder_play_record',
                nonce: params.nonce,
            },
            success: function (res) {
                playRecord(res);
            },
            error: function (errorThrown) {
                console.log(errorThrown);
            }
        });
    });

    function playRecord(_events) {
        if (!_events) return;
        let datas = [];
        for (e of _events) {
            let data = e.data;
            data = LZUTF8.decompress(data, {inputEncoding:'Base64'});
            data = JSON.parse(data);
            datas.push(data);
        }
        $TB_ajaxContent = jQuery('#TB_ajaxContent');
        let w = parseInt($TB_ajaxContent.width());
        let h = parseInt($TB_ajaxContent.height()) - 80;
        let events = [];
        for (let i = 0; i < datas.length; i++) {
            for (let j = 0; j < datas[i].length; j++) {
                events.push(datas[i][j]);
            }
        }
        new rrwebPlayer({
            target: document.getElementById('TB_ajaxContent'),
            data: {
                events,
                autoPlay: true,
                width:w,
                height:h
            },
        });
    }

    jQuery('#spyDeleteAllrecords').click(function () {
        if (confirm('Are you totally sure to delete ALL records ?')) {
            jQuery.ajax({
                url: params.ajaxurl,
                method: 'POST',
                data: {
                    'action': 'screenRecorder_delete_all_records',
                    'nonce': params.nonce,
                },
                success: function () {
                    drawTable();
                },
                error: function (errorThrown) {
                    console.log(errorThrown);
                }
            });
        }
    })
})(params);