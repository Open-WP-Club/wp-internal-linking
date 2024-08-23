jQuery(document).ready(function ($) {
  $("#save-link-words").on("click", function (e) {
    e.preventDefault();
    var linkedWords = [];
    $('input[name="link_word"]:checked').each(function () {
      linkedWords.push($(this).val());
    });

    $.ajax({
      url: ailAjax.ajaxurl,
      type: "POST",
      data: {
        action: "save_link_words",
        linked_words: linkedWords,
      },
      success: function (response) {
        alert("Words saved successfully");
      },
      error: function () {
        alert("An error occurred. Please try again.");
      },
    });
  });
});
