var wheel_hex_colours = ["#3369e8", "#d50f25", "#eeb211", "#009925"];
var graph_array = [];
var wheel_limit = false;
var ticker_sound_global = true;
var slices = [];
var wheelobject = {
    slices: [{
            text: "",
            value: 0,
            background: "#3369e8",
        },
        {
            text: "",
            value: 0,
            background: "#d50f25",
        },
        {
            text: "",
            value: 0,
            background: "#eeb211",
        },
        {
            text: "",
            value: 0,
            background: "#009925",
        }
    ],
    width: 390,
    frame: 20,
    type: "spin",
    text: {
        color: "#ccc",
        offset : 14,
        letterSpacing: 8,
        orientation: 'h',
        arc: true
    },    
    line: {
        width: 1,
        color: "#ffffff"
    },
    outer: {
        width: 2,
        color: "#ffffff"
    },
    inner: {
        width: 0,
        color: "#ffffff"
    },
    center: {
        width: 14,
        rotate: false
    },
};
var entityMap = {
    "&": "&#38;",
    "<": "&#60;",
    ">": "&#62;",
    "\"": "&#34;",
    "'": "&#39;",
    "/": "&#92;",
    "`": "&#96;",
    "=": "&#61;"
};
$( window ).on( "load", function() {
        $("#wheel_input_data").val("");
        $("#text_wrapper").html("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;");
        $('.wheel').superWheel(wheelobject);
       
    });
function escapeHtml(string) {
    return String(string).replace(/[&<>"'`=\/]/g, function(s) {
        return entityMap[s];
    });
}
var tick = new Audio('../asset/audio/tick.mp3');
var win_new_audio = new Audio('../asset/audio/winner.mp3');
var waiting_for_spinining = new Audio('../asset/audio/drum.mp3');
function setnull(obj) {
    Object.keys(obj).forEach(function(index) {
        obj[index] = null;
    });
}

function input_data_to_array(datatext) {
    var arraytemp = datatext.replace(/\r\n|\n\r|\n|\r/g, "\n").split("\n");
    if (arraytemp == null || arraytemp.length == null || arraytemp.length < 1) {
        return [];
    }
    var realarray = [];
    for (var i = 0, l = arraytemp.length; i < l; i++) {
        if (arraytemp[i] != null && arraytemp[i] != "" && arraytemp[i].trim() != "") {
            realarray.push(arraytemp[i].trim());
        }
    }
    if (realarray == null || realarray.length == null || realarray.length < 1) {
        return [];
    }
    return realarray;
    // var datatext=$.trim(datatext);
    // if(datatext.indexOf(",") >= 0){
    //     var datatext=datatext.split(',');        
    //     datatext=datatext.filter(function( data ) {
    //         if(data != ''){
    //             return data;
    //         }
    //     })
    //     return datatext
    // }else{        
    //     var datatext=new Array(datatext)
    //     datatext=datatext.filter(function( data ) {
    //         if(data != ''){
    //             return data;
    //         }
    //     })
    //     return datatext;
    // }
}
function whellinputspinfunc() {
    if (!wheel_limit) {
        graph_array = input_data_to_array($("#wheel_input_data").val());
        if (graph_array != null && graph_array.length != null && graph_array.length > 1) {
            slices = [];
            var incr_ = 0;
            var operator_ = 0;
            var colour_ = "#3369e8";
            var text_col = "#ffffff";
            var randomnum = 0;
            if (parseInt(graph_array.length) > 90) {
                while (parseInt(graph_array.length) > 90) {
                    randomnum = getRandomInt(parseInt(graph_array.length));
                    graph_array.splice(randomnum, 1);
                }
            }
            for (var i = 0, l = graph_array.length; i < l; i++) {
                try {
                    operator_ = incr_ % 4;
                    colour_ = wheel_hex_colours[operator_];
                    if (operator_ < 2) {
                        text_col = "#ffffff";
                    } else {
                        text_col = "#000000";
                    }
                } catch (ex) {}
                incr_++;
                slices.push({
                    text: graph_array[i],
                    background: colour_,
                    color: text_col,
                    value: 0,
                });
            }
            wheelobject.slices = slices;
            $(".wheel").superWheel(wheelobject);
        }
    }
}
function getRandomInt(max) {
    return Math.floor(Math.random() * Math.floor(max));
}

$("#wrapper_start_it").click(function(event) {
    if (!wheel_limit) {
        if (graph_array != null && graph_array.length != null && Array.isArray(graph_array) && parseInt(graph_array.length) > 1) {
            if (!ticker_sound_global) {
                if (waiting_for_spinining != null && waiting_for_spinining.currentTime != null) {
                    waiting_for_spinining.currentTime = 0;
                }
                waiting_for_spinining.play().catch(function(error) {});
            }
            if (!ticker_sound_global) {
                setTimeout(function() {
                    waiting_for_spinining.pause();
                    if (waiting_for_spinining != null && waiting_for_spinining.currentTime != null) {
                        waiting_for_spinining.currentTime = 0;
                    }
                    $(".wheel").superWheel("start", "value", 0);
                }, 3180);
            } else {
                $(".wheel").superWheel("start", "value", 0);
            }
            $('.wheel').superWheel('onComplete', function(results, spinCount, now) {
                if (!ticker_sound_global) {
                    win_new_audio.play().catch(function(error) {});
                }
                if (results != null && results.text != null && results.text != "") {
                    $("#text_wrapper").html("The winner is: <b>" + results.text + "</b>");
                }
            });
            $('.wheel').superWheel('onStep', function(results, slicePercent, circlePercent) {
                tick.play().catch(function(error) {});
            });
        }
    }
});
var inputchange = true;
$("#wheel_input_data").on("change paste keyup input propertychange", function() {
    if (inputchange) {
        inputchange = false;
        whellinputspinfunc();
        inputchange = true;
    }
});
$("#host_raffle").click(function() {
    $("#wheel_input_data").val("");
    $("#text_wrapper").html("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;");
    $('.wheel').superWheel(wheelobject);
    $(".hideall").hide();
    $("#step9").show();
});
