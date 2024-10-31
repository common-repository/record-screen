(function (params) {
    let seconds = 5;
    let events = [];
    let spy = rrweb.record({
        emit(event) {
            events.push(event);
        },
    });

    function save() {
        if (!events.length) {
            return;
        }
        let data = LZUTF8.compress(JSON.stringify(events), {outputEncoding: "Base64"} );
        jQuery.ajax({
            url: params.ajaxurl,
            method:'POST',
            data: {
                'uid'       : params.uid,
                'post_id'   : params.post.ID,
                'nonce'     : params.nonce,
                'url'       : params.url,
                'session'   : params.session,
                'agent'     : params.agent,
                'ip'        : params.ip,
                'data'      : data,
                'action'    : 'screenRecorder_insert_data',
                'seconds'   : seconds
            },
            success:function(data) {
                events = [];
            },
            error: function(errorThrown){
                console.log(errorThrown);
            }
        });

    }
    jQuery(window).on('beforeunload', function(){
        save();
    });
    setInterval(save, seconds * 1000);
})(spyParameters);
