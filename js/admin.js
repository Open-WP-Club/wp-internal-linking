jQuery(document).ready(function ($) {
  $("#analyze-all-posts").on("click", function (e) {
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
        action: "analyze_all_posts",
      },
      success: function (response) {
        if (response.success) {
          var wordUsage = response.data;
          var html = '<table class="wp-list-table widefat fixed striped">';
          html += "<thead><tr><th>Word</th><th>Pages</th></tr></thead><tbody>";

          for (var word in wordUsage) {
            html += "<tr><td>" + word + "</td><td>";
            wordUsage[word].forEach(function (page) {
              html += '<a href="' + page.url + '">' + page.title + "</a><br>";
            });
            html += "</td></tr>";
          }

          html += "</tbody></table>";
          $results.html(html);
        } else {
          $results.html("<p>Failed to analyze posts. Please try again.</p>");
        }
      },
      error: function () {
        $results.html("<p>An error occurred. Please try again.</p>");
      },
      complete: function () {
        $button.prop("disabled", false).text("Analyze All Posts");
      },
    });
  });
});
