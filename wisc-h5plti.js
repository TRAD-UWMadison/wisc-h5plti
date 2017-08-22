

(function($) {

    var questions = {};
    var blogID;
    var postID;
    var endpoint;
    var auth;
    var processStatementsURL = "";
    var h5purl = "";
    var ids_array;

    var completedVerb = "http%3A%2F%2Fadlnet.gov%2Fexpapi%2Fverbs%2Fcompleted";
    var answeredVerb = "http%3A%2F%2Fadlnet.gov%2Fexpapi%2Fverbs%2Fanswered";
    var activityURL = "";

    $(document).ready(function() {
        endpoint = $("#ll-submit-endpoint").val();
        activityURL = $("#ll-submit-activity").val();
        var authEncode = $("#ll-submit-auth").val();
        processStatementsURL = $("#ll-process-xapi").val();
        blogID = $("#ll-submit-blog").val();
        postID = $("#ll-submit-post").val();
        h5purl = $("#ll-submit-h5purl").val();
        auth = "Basic " + authEncode;

        var sinceDate = new Date(1);
        var untilDate = new Date(1);
        var baselineDate = new Date(86400);
        // var requestAttemptedStatements = endpoint + "statements?verb=http%3A%2F%2Fadlnet.gov%2Fexpapi%2Fverbs%2Fattempted&activity=" + activity + "&limit=500&format=exact";
        // var requestFailedStatments = endpoint + "statements?verb=http%3A%2F%2Fadlnet.gov%2Fexpapi%2Fverbs%2Ffailed&activity=" + activity + "&limit=500&format=exact";
        // var requestPassedStatments = endpoint + "statements?verb=http%3A%2F%2Fadlnet.gov%2Fexpapi%2Fverbs%2Fpassed&activity=" + activity + "&limit=500&format=exact";
        // var requestCompletedStatements = endpoint + "statements?verb=http%3A%2F%2Fadlnet.gov%2Fexpapi%2Fverbs%2Fcompleted&activity=" + activity

        $("#since").on("change", function () {
            sinceDate = new Date($(this).val());
        });

        $("#until").on("change", function () {
            untilDate = new Date($(this).val());
        });

        $("#ll-submit-attempted").click(function() {
            finishedIDs = 0;
            ll_statements = [];
            var div = $("#ll-statements");
            div.html("<h3>Fetching Grades.  Please wait.</h3>");

            if(sinceDate > untilDate ) {
                div = $("#ll-statements");
                div.html("<h3 class='ll-warning'>Invalid Date Combination Selected!</h3>");
                return;
            }

            if(sinceDate > baselineDate && untilDate > baselineDate) {
                var isoSince = sinceDate.toISOString().substr(0, 10);
                var isoUntil = untilDate.toISOString().substr(0, 10);
                var sinceParameter = "&since=" + isoSince + "T00:00:00.0000000Z";
                var untilParameter = "&until=" + isoUntil + "T00:00:00.0000000Z";
            }

            var ids = $('#ids').val();
            ids_array = ids.split(',');
            if (ids_array.length < 1 || ids_array[0] == ""){
                div = $("#ll-statements");
                div.html("<h3 class='ll-warning'>No H5P ids!  Please add the id's separated by commas.</h3>");
                return;
            }
            console.log(ids_array);
            for (var i = 0; i < ids_array.length; i++){
                var request = $.ajax({
                    url:buildRequestURL(completedVerb, ids_array[i], sinceParameter, untilParameter),
                    headers: {
                        'authorization': auth,
                        'x-experience-api-version' :'1.0.1'
                    }
                })
                request.success(handleRequest);
                request.error(requestFailed)
                var request = $.ajax({
                    url:buildRequestURL(answeredVerb, ids_array[i], sinceParameter, untilParameter),
                    headers: {
                        'authorization': auth,
                        'x-experience-api-version' :'1.0.1'
                    }
                })
                request.success(handleRequest);
                request.error(requestFailed)
            }
        });

    });


    var ll_statements = [];
    var handleRequest = function(msg){
        console.log(msg);
        ll_statements = ll_statements.concat(msg['statements'])
        if (msg['more'] !== '' && msg['statements'].length !== 0){
            var endpointURL = new URL(endpoint);
            var requestURL = endpointURL.origin + msg['more'];
            var request = $.ajax({
                url:requestURL,
                headers: {
                    'authorization': auth,
                    'x-experience-api-version' :'1.0.1'
                }
            })
            request.success(handleRequest);
            request.error(requestFailed);
        } else {
            finishedIDs++;
            sendGrades();
        }
    }

    var finishedIDs;
    var sendGrades = function(){
        if (finishedIDs < ids_array.length * 2) return;
        var statements = {}
        data = {};

        data.blog = blogID;
        data.post = postID;
        data.statements = ll_statements;
        
        data.grading = $("#ll-submit-grading").val()

        console.log(data);
        statements.data = JSON.stringify(data);

        var request = $.ajax({
            type: "POST",
            url: processStatementsURL,
            data: statements,
            dataType: "json",
        });
        request.done(function(msg) {
            console.log( msg );
            div = $("#ll-statements")
            if (msg.error){
                div.html("<h3 class='ll-warning'>"+msg.error+"</h3>");
                return;
            }
            var str = '<table><tr><th>User</th><th>Question</th><th>Attempt Time</th><th>Score</th><th>Max Points</th><th>Percent</th></tr>';

            for (var userName in msg.userData){
                for (var question in msg.userData[userName]){
                    if (msg.userData[userName][question].target != null) {
                        var d = new Date(msg.userData[userName][question].target.timestamp);
                        var datestring = d.getDate()  + "-" + (d.getMonth()+1) + "-" + d.getFullYear() + " " +
                            d.getHours() + ":" + d.getMinutes();
                        str += '<tr>';
                        str += '<td>' + userName + '</td>';
                        str += '<td>' + msg.userData[userName][question].target.question + '</td>';
                        str += '<td>' + datestring  + '</td>';
                        str += '<td>' + msg.userData[userName][question].target.rawScore + '</td>';
                        str += '<td>' + msg.userData[userName][question].target.maxScore + '</td>';
                        str += '<td>' + msg.userData[userName][question].target.score * 100 + '%</td>';
                        str += '</tr>'
                    }
                }
                str += '<tr>';
                str += '<td>' + userName + '</td>';
                str += '<td>Total Grade</td>';
                str += '<td></td>';
                str += '<td>' + msg.userData[userName].totalScore + '</td>';
                str += '<td>' + msg.maxGrade + '</td>';
                str += '<td>' + msg.userData[userName].percentScore * 100 + '%</td>';
                str += '</tr>'
                //str += '<tr><td>'+userName+'</td><td>'+msg.userData[userName].target.timestamp+'</td><td style="text-align:center">'+msg.userData[userName].target.score*100+'%</td></tr>';
            }
            str += '</table>'
            div.html(str)

        });

        request.fail(function(jqXHR, textStatus) {
            console.log( "Request failed: " + textStatus );
        });
    }


    var requestFailed = function(jqXHR, textStatus){
        div = $("#ll-statements");
        div.html("<h3 class='ll-warning'>There was an error sending grades.</h3><h3 class='ll-warning'>"+textStatus+"</h3>");
    }
    
    var buildRequestURL = function (verb, activityID, since, until){
        var url = endpoint + "statements?verb=" +verb + "&activity=" + encodeURIComponent(activityURL + activityID) + "&limit=500&format=exact";
        if (since != null && until != null){
            url += since + until;
        }
        return url;
    }

})(jQuery)
