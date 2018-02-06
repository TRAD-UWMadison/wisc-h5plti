

(function($) {

    var $button,
        $gradingScheme,
        $h5pIDsString,
        $llStatements,
        $since,
        $until;

    var apply_bindings = function() {
        $button = $('#chapter_grade_sync_fields_submit');
        $gradingScheme = $("#chapter_grade_sync_fields\\[grading_scheme\\]");
        $h5pIDsString = $("#chapter_grade_sync_fields\\[h5p_ids_string\\]");
        $llStatements = $('#ll-statements');
        $since = $("#chapter_grade_sync_fields\\[since\\]");
        $until = $("#chapter_grade_sync_fields\\[until\\]");
        $button.on('click', syncGrades);
    };

    var syncGrades = function(e) {
        var data = {
            action: chapterGradeSync.action,
            ajaxNonce: chapterGradeSync.ajaxNonce,
            blogID : chapterGradeSync.blogID,
            gradingScheme : $gradingScheme.val(),
            h5pIDsString : $h5pIDsString.val(),
            postID : chapterGradeSync.postID,
            since : $since.val(),
            until : $until.val()
        };
        $.ajax({
            data: data,
            method: 'POST',
            success: syncGradesSuccess,
            error: syncGradesError,
            complete: syncGradesComplete,
            url: ajaxurl
        });
        // Update the button
        $button.val("Sending Grades...");
        $button.prop('disabled', true);
    };
    var syncGradesSuccess = function(data, textStatus, jqXHR) {
        var llResults = JSON.parse(data);
        displayLLResults(llResults);
    };
    var syncGradesError = function(jqXHR, textStatus, errorThrown) {
        displayLLResults({
            error: 'Failed to communicate with server'
        })
    };
    var syncGradesComplete = function(jqXHR, textStatus) {
        $button.val('Send Grades to LMS');
        $button.prop('disabled', false);
    };

    var displayLLResults = function(llResults) {
        var html = "<hr/><h4>Grade Sync Response</h4>";
        if (llResults.error){
            html += "<h3 class='ll-warning'>"+llResults.error+"</h3>";
        } else {
            html += "<table><tr><th>User</th><th>Question</th><th>Attempt Time</th><th>Score</th><th>Max Points</th><th>Percent</th></tr>";
            for (var userName in llResults.userData){
                for (var question in llResults.userData[userName]){
                    if (llResults.userData[userName][question].target != null) {
                        var d = new Date(llResults.userData[userName][question].target.timestamp);
                        var datestring = d.getDate()  + "-" + (d.getMonth()+1) + "-" + d.getFullYear() + " " +
                            d.getHours() + ":" + d.getMinutes();
                        html += '<tr>';
                        html += '<td>' + userName + '</td>';
                        html += '<td>' + llResults.userData[userName][question].target.question + '</td>';
                        html += '<td>' + datestring  + '</td>';
                        html += '<td>' + llResults.userData[userName][question].target.rawScore + '</td>';
                        html += '<td>' + llResults.userData[userName][question].target.maxScore + '</td>';
                        html += '<td>' + llResults.userData[userName][question].target.score * 100 + '%</td>';
                        html += '</tr>'
                    }
                }
                html += '<tr>';
                html += '<td>' + userName + '</td>';
                html += '<td>Total Grade</td>';
                html += '<td></td>';
                html += '<td>' + llResults.userData[userName].totalScore + '</td>';
                html += '<td>' + llResults.maxGrade + '</td>';
                html += '<td>' + llResults.userData[userName].percentScore * 100 + '%</td>';
                html += '</tr>';
            }
            html += '</table>';
        }
        $llStatements.html(html);
    };


    $(document).ready(function() {

        apply_bindings();

    });

})(jQuery);
