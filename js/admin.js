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

  // Toggle blacklist visibility
  $("#toggle-blacklist").on("click", function () {
    $("#blacklist-content").toggle();
  });

  // Add word to blacklist
  $("#add-blacklist-word").on("click", function () {
    var word = $("#new-blacklist-word").val().trim();
    if (word) {
      $.ajax({
        url: ailAjax.ajaxurl,
        type: "POST",
        data: {
          action: "add_blacklist_word",
          word: word,
        },
        success: function (response) {
          if (response.success) {
            $("#new-blacklist-word").val("");
            refreshBlacklist();
          } else {
            alert("Failed to add word to blacklist.");
          }
        },
      });
    }
  });

  // Remove word from blacklist
  $(document).on("click", ".remove-blacklist-word", function () {
    var word = $(this).data("word");
    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "remove_blacklist_word",
        word: word,
      },
      success: function (response) {
        if (response.success) {
          refreshBlacklist();
        } else {
          alert("Failed to remove word from blacklist.");
        }
      },
    });
  });

  // Search blacklist
  var searchTimer;
  $("#search-blacklist").on("input", function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
      refreshBlacklist();
    }, 300);
  });

  function refreshBlacklist(page = 1) {
    var search = $("#search-blacklist").val();
    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "search_blacklist",
        search: search,
        page: page,
      },
      success: function (response) {
        if (response.success) {
          $("#blacklist-words").html(response.data.blacklist_html);
          $("#blacklist-pagination").html(response.data.pagination_html);
        }
      },
    });
  }

  // Handle pagination clicks
  $(document).on("click", "#blacklist-pagination a", function (e) {
    e.preventDefault();
    var page = $(this).attr("href").split("blacklist_page=")[1];
    refreshBlacklist(page);
  });
});
