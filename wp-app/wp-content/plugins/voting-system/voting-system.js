jQuery(document).ready(function ($) {
  $(".vote-button").click(function () {
    var button = $(this);
    var post_id = button.parent().data("post-id");
    var vote = button.data("vote");

    $.post(
      voting_system.ajax_url,
      {
        action: "submit_vote",
        post_id: post_id,
        vote: vote,
      },
      function (response) {
        if (response.success) {
          // The vote was successful
          var resultsYes = button
            .parent()
            .siblings(".article-helpful")
            .find(".datatarget1");
          var resultsNo = button
            .parent()
            .siblings(".article-helpful")
            .find(".datatarget2");

          resultsYes.text(response.data.results.yes + "%");
          resultsNo.text(response.data.results.no + "%");
          var messages = button.parent();
          messages.text(response.data.message);
        } else {
          // There was an error
          button.parent().text(response.data);
        }
      }
    );
  });
});
