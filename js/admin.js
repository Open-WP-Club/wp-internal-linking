jQuery(document).ready(function ($) {
  $("#analyze-all-content").on("click", function (e) {
    e.preventDefault();
    var $button = $(this);
    var $results = $("#analysis-results");

    $button.prop("disabled", true).text("Analyzing...");
    $results.html(
      "<p>Analysis in progress. This may take a few moments...</p>"
    );

    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "analyze_all_content",
      },
      success: function (response) {
        if (response.success) {
          $results.html(response.data);
        } else {
          $results.html("<p>Failed to analyze content. Please try again.</p>");
        }
      },
      error: function () {
        $results.html("<p>An error occurred. Please try again.</p>");
      },
      complete: function () {
        $button.prop("disabled", false).text("Analyze All Content");
      },
    });
  });
});
